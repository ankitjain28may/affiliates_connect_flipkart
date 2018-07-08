<?php

namespace Drupal\affiliates_connect_flipkart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\affiliates_connect\Entity\AffiliatesProduct;
use Drupal\affiliates_connect\AffiliatesNetworkManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * Use Native API of Flipkart to collect data.
 */
class FlipkartNativeController extends ControllerBase {

  /**
   * The affiliates network manager.
   *
   * @var \Drupal\affiliates_connect\AffiliatesNetworkManager
   */
  private $affiliatesNetworkManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.affiliates_network')
    );
  }

  /**
   * AffiliatesConnectController constructor.
   *
   * @param \Drupal\affiliates_connect\AffiliatesNetworkManager $affiliatesNetworkManager
   *   The affiliates network manager.
   */
  public function __construct(AffiliatesNetworkManager $affiliatesNetworkManager) {
    $this->affiliatesNetworkManager = $affiliatesNetworkManager;
  }

  /**
   * Start Batch Processing.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request Object
   */
  public function startBatch(Request $request)
  {
    // If enabled native_apis
    if (!$this->config('affiliates_connect_flipkart.settings')->get('native_api')) {
      drupal_set_message($this->t('Configure flipkart native api to import data'), 'error', FALSE);
      return $this->redirect('affiliates_connect_flipkart.settings');
    }
    $params = $request->query->all();
    $category = $params['category'];
    $batch = [];
    // Fetch all the categories
    $categories = $this->categories();
    $operations = [];
    $title = '';
    if ($category) {
      $operations[] = [[get_called_class(), 'startBatchImporting'], [$category, $categories[$category]]];
      $title = $this->t('Importing products from @category', ['@category' => $category]);
    } else {
      foreach ($categories as $key => $value) {
        $operations[] = [[get_called_class(), 'startBatchImporting'], [$key, $value]];
      }
      $title = $this->t('Importing products from @num categories', ['@num' => count($categories)]);
    }
    $batch = [
      'title' => $title,
      'init_message' => $this->t('Importing..'),
      'operations' => $operations,
      'progressive' => TRUE,
      'finished' => [get_called_class(), 'batchFinished'],
    ];
    batch_set($batch);
    return batch_process('/admin/config/affiliates-connect/overview');
  }


  /**
   * Batch for Importing of products.
   *
   * @param string $key
   * @param string $value
   * @param $context
   */
  public static function startBatchImporting($key, $value, &$context) {
    $categories = Self::products($value);
    $context['results']['processed']++;
    $context['message'] = 'Completed importing category : ' . $key;
  }

  /**
   * Batch finished callback.
   *
   * @param $success
   * @param $results
   * @param $operations
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
     drupal_set_message(t("The products are successfully imported from flipkart."));
    }
    else {
      $error_operation = reset($operations);
      drupal_set_message(t('An error occurred while processing @operation with arguments : @args', array('@operation' => $error_operation[0], '@args' => print_r($error_operation[0], TRUE))), 'error');
    }
  }

  /**
   * Fetching categories from the Category API.
   *
   * @return array
   *   A collection of categories along with category url
   */
  public function categories()
  {
    $flipkart = $this->affiliatesNetworkManager->createInstance('affiliates_connect_flipkart');
    $config = $this->config('affiliates_connect_flipkart.settings');

    $fk_affiliate_id = $config->get('flipkart_tracking_id');
    $token = $config->get('flipkart_token');

    $header = [
      'Fk-Affiliate-Id' => $fk_affiliate_id,
      'Fk-Affiliate-Token' => $token,
      'Accept' => 'application/json',
    ];

    $url = 'https://affiliate-api.flipkart.net/affiliate/api/' . $fk_affiliate_id . '.json';
    // $client = new Client();
    $response = $flipkart->get($url, ['headers' => $header]);
    $body = $response->getBody();
    $body = json_decode($body, true);

    $categories = [];
    foreach ($body['apiGroups']['affiliate']['apiListings'] as $key => $value) {
      $categories[$key] = $value['availableVariants']['v1.1.0']['get'];
    }
    return $categories;
  }

  /**
   * Collect products data from Product APIs.
   *
   * @param string $product_url
   *   An url where request is to be made
   */
  public function products($product_url)
  {
    $flipkart = \Drupal::service('plugin.manager.affiliates_network')->createInstance('affiliates_connect_flipkart');

    $config = \Drupal::configFactory()->get('affiliates_connect_flipkart.settings');
    $fk_affiliate_id = $config->get('flipkart_tracking_id');
    $token = $config->get('flipkart_token');

    $header = [
      'Fk-Affiliate-Id' => $fk_affiliate_id,
      'Fk-Affiliate-Token' => $token,
      'Accept' => 'application/json',
    ];
    $response = $flipkart->get($product_url, ['headers' => $header]);
    $products_data = $response->getBody();
    $products_data = json_decode($products_data, true);

    foreach ($products_data['products'] as $key => $value) {
      try {
        Self::createOrUpdate($value, $config);
      }
      catch (Exception $e) {
        echo $e->getMessage();
      }
    }
  }

  /**
   * Create if not found else update the existing.
   *
   * @param array $value
   *  product data
   * @param ImmutableConfig $config
   *  configuration of the plugin
   */
  public function createOrUpdate(array $value, ImmutableConfig $config)
  {
    $product_id = $value['productBaseInfoV1']['productId'];
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('affiliates_product')
      ->loadByProperties(['product_id' => $product_id]);
    $product = reset($nodes);

    if (!$product) {
      $product = AffiliatesProduct::create([
        'uid' => \Drupal::currentUser()->id(),
        'name' => $value['productBaseInfoV1']['title'],
        'plugin_id' => 'affiliates_connect_flipkart',
        'product_id' => $value['productBaseInfoV1']['productId'],
        'product_description' => $value['productBaseInfoV1']['productDescription'],
        'image_urls' => $value['productBaseInfoV1']['imageUrls']['400x400'],
        'product_family' => $value['productBaseInfoV1']['categoryPath'],
        'currency' => $value['productBaseInfoV1']['maximumRetailPrice']['currency'],
        'maximum_retail_price' => $value['productBaseInfoV1']['maximumRetailPrice']['amount'],
        'vendor_selling_price' => $value['productBaseInfoV1']['flipkartSellingPrice']['amount'],
        'vendor_special_price' => $value['productBaseInfoV1']['flipkartSpecialPrice']['amount'],
        'product_url' => $value['productBaseInfoV1']['productUrl'],
        'product_brand' => $value['productBaseInfoV1']['productBrand'],
        'in_stock' => $value['productBaseInfoV1']['inStock'],
        'cod_available' => $value['productBaseInfoV1']['codAvailable'],
        'discount_percentage' => $value['productBaseInfoV1']['discountPercentage'],
        'offers' => implode(',', $value['productBaseInfoV1']['offers']),
        'size' => $value['productBaseInfoV1']['attributes']['size'],
        'color' => $value['productBaseInfoV1']['attributes']['color'],
        'seller_name' => $value['productShippingInfoV1']['sellerName'],
        'seller_average_rating' => $value['productShippingInfoV1']['sellerAverageRating'],
        'additional_data' => '',
        'status' => 1,
      ]);
      $product->save();
      return;
    }
    if ($config->get('full_content')) {
      $product->setName($value['productBaseInfoV1']['title']);
      $product->setProductDescription($value['productBaseInfoV1']['productDescription']);
      $product->setImageUrls($value['productBaseInfoV1']['imageUrls']['400x400']);
      $product->setCurrency($value['productBaseInfoV1']['maximumRetailPrice']['currency']);
      $product->setMaximumRetailPrice($value['productBaseInfoV1']['maximumRetailPrice']['amount']);
      $product->setVendorSellingPrice($value['productBaseInfoV1']['flipkartSellingPrice']['amount']);
      $product->setVendorSpecialPrice($value['productBaseInfoV1']['flipkartSpecialPrice']['amount']);
      $product->setProductUrl($value['productBaseInfoV1']['productUrl']);
      $product->setProductAvailability($value['productBaseInfoV1']['inStock']);
      $product->setProductCodAvailability($value['productBaseInfoV1']['codAvailable']);
      $product->setDiscount($value['productBaseInfoV1']['discountPercentage']);
      $product->setOffers(implode(',', $value['productBaseInfoV1']['offers']));
      $product->setSize($value['productBaseInfoV1']['attributes']['size']);
      $product->setColor($value['productBaseInfoV1']['attributes']['color']);
      $product->setSellerName($value['productShippingInfoV1']['sellerName']);
      $product->setSellerAverageRating($value['productShippingInfoV1']['sellerAverageRating']);
    }
    if ($config->get('price')) {
      $product->setCurrency($value['productBaseInfoV1']['maximumRetailPrice']['currency']);
      $product->setMaximumRetailPrice($value['productBaseInfoV1']['maximumRetailPrice']['amount']);
      $product->setVendorSellingPrice($value['productBaseInfoV1']['flipkartSellingPrice']['amount']);
      $product->setVendorSpecialPrice($value['productBaseInfoV1']['flipkartSpecialPrice']['amount']);
      $product->setDiscount($value['productBaseInfoV1']['discountPercentage']);
    }
    if ($config->get('available')) {
      $product->setProductAvailability($value['productBaseInfoV1']['inStock']);
    }
    if ($config->get('size')) {
      $product->setSize($value['productBaseInfoV1']['attributes']['size']);
    }
    if ($config->get('color')) {
      $product->setColor($value['productBaseInfoV1']['attributes']['color']);
    }
    if ($config->get('offers')) {
      $product->setOffers(implode(',', $value['productBaseInfoV1']['offers']));
    }
    $product->save();
  }
}
