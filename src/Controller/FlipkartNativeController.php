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

    if (!$this->config('affiliates_connect_flipkart.settings')->get('data_storage')) {
      drupal_set_message($this->t('Enable Data Storage for storing data'), 'error', FALSE);
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
        $product = Self::buildImportData($value);
        AffiliatesProduct::createOrUpdate($product, $config);
      }
      catch (Exception $e) {
        echo $e->getMessage();
      }
    }
  }

  /**
   * To create a product array with appropriate key-value pair.
   *
   * @param array $product_data
   *
   * @return array
   *
   */
  public function buildImportData($product_data) {
    $product = [
      'name' => $product_data['productBaseInfoV1']['title'],
      'plugin_id' => 'affiliates_connect_flipkart',
      'product_id' => $product_data['productBaseInfoV1']['productId'],
      'product_description' => $product_data['productBaseInfoV1']['productDescription'],
      'image_urls' => $product_data['productBaseInfoV1']['imageUrls']['400x400'],
      'product_family' => $product_data['productBaseInfoV1']['categoryPath'],
      'currency' => $product_data['productBaseInfoV1']['maximumRetailPrice']['currency'],
      'maximum_retail_price' => $product_data['productBaseInfoV1']['maximumRetailPrice']['amount'],
      'vendor_selling_price' => $product_data['productBaseInfoV1']['flipkartSellingPrice']['amount'],
      'vendor_special_price' => $product_data['productBaseInfoV1']['flipkartSpecialPrice']['amount'],
      'product_url' => $product_data['productBaseInfoV1']['productUrl'],
      'product_brand' => $product_data['productBaseInfoV1']['productBrand'],
      'in_stock' => $product_data['productBaseInfoV1']['inStock'],
      'cod_available' => $product_data['productBaseInfoV1']['codAvailable'],
      'discount_percentage' => $product_data['productBaseInfoV1']['discountPercentage'],
      'offers' => implode(',', $product_data['productBaseInfoV1']['offers']),
      'size' => $product_data['productBaseInfoV1']['attributes']['size'],
      'color' => $product_data['productBaseInfoV1']['attributes']['color'],
      'seller_name' => $product_data['productShippingInfoV1']['sellerName'],
      'seller_average_rating' => $product_data['productShippingInfoV1']['sellerAverageRating'],
      'additional_data' => '',
    ];
    return $product;
  }
}
