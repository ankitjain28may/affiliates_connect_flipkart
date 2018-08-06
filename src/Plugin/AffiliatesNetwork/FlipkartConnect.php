<?php

namespace Drupal\affiliates_connect_flipkart\Plugin\AffiliatesNetwork;

use Drupal\affiliates_connect\AffiliatesNetworkBase;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Url;

/**
 * Contains Plugin ID and Plugin definition info for affiliates_connect_flipkart.
 *
 * @AffiliatesNetwork(
 *  id = "affiliates_connect_flipkart",
 *  label = @Translation("Flipkart"),
 *  description = @Translation("Plugin provided by affiliates_connect_flipkart."),
 * )
 */
class FlipkartConnect extends AffiliatesNetworkBase implements FlipkartConnectInterface {
  /**
   * Stores the options used in this request.
   *
   * @var array
   */
  protected $options = [];

  /**
   * Stores the headers used in this request.
   *
   * @var array
   */
  protected $headers = [];

  /**
   * Stores the generated signature link.
   *
   * @var array
   */
  protected $url;

  /**
   * Stores the results of this request when executed.
   *
   * @var array
   */
  protected $results = [];

  /**
   * Stores the raw results of this request when executed.
   *
   * @var array
   */
  protected $raw_results = [];

  /**
   * The tracking id for the Product Advertising API account.
   *
   * @var string
   */
  protected $tracking_id;

  /**
   * The token for the Product Advertising API account.
   *
   * @var string
   */
  protected $token;

  /**
   * To switch between categories and products clensing.
   * @var boollen
   */
  protected $toggle;


  /**
   * The domain of the endpoint for making Product Advertising API requests.
   *
   * @var string
   */
  protected $flipkart_request_root = 'affiliate-api.flipkart.net/affiliate';

  /**
   * Type of response format like xml or json.
   * @var string
   */
  protected $response_format;


  /**
   * Set Credentials.
   *
   * @param string $tracking_id
   *   The tracking ID for the Product Advertising API account.
   * @param string $token
   *   The token for making Product Advertising API requests.
   * @param string $response_format
   *   Response format like XML, JSON
   */
  public function setCredentials(string $tracking_id, string $token, string $response_format = 'json') {
    $this->tracking_id = $tracking_id;
    $this->token = $token;
    $this->response_format = $response_format;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareLink(string $endpoint) {
    if (empty($this->headers['Fk-Affiliate-Id'])) {
      if (empty($this->tracking_id)) {
        throw new \InvalidArgumentException('Missing Tracking Id. Need to be passed as an option or set in the constructor.');
      }
      else {
        $this->setHeader('Fk-Affiliate-Id', $this->tracking_id);
      }
    }

    if (empty($this->headers['Fk-Affiliate-Token'])) {
      if (empty($this->token)) {
        throw new \InvalidArgumentException('Missing Token. Need to be passed as an option or set in the constructor.');
      }
      else {
        $this->setHeader('Fk-Affiliate-Token', $this->token);
      }
    }

    // Add a Service Version.
    $this->headers['Accept'] = 'application/json';
    $url = Url::fromUri($endpoint, ['query' => $this->options]);
    $this->url = $url->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $data = $this->get($this->url, ['headers' => $this->headers]);
    if (isset($data) && $data->getStatusCode() == 200) {
      $JSON = json_decode($data->getBody(), true);
      $this->raw_results = $JSON;
      if (!isset($JSON['products'])) {
        $this->raw_results = [];
        $this->raw_results['products'][] = $JSON;
      }
      $this->cleanResult();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOption($name, $value) {
    if (empty($name)) {
      throw new \InvalidArgumentException('Invalid option name: ' . $name);
    }
    $this->options[$name] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions(array $options) {
    foreach($options as $name => $value) {
      if (empty($name)) {
        throw new \InvalidArgumentException('Invalid option name: ' . $name);
      }
      $this->setOption($name, $value);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeader($name, $value) {
    if (empty($name)) {
      throw new \InvalidArgumentException('Invalid header name: ' . $name);
    }
    $this->headers[$name] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeaders(array $headers) {
    foreach($headers as $name => $value) {
      if (empty($name)) {
        throw new \InvalidArgumentException('Invalid header name: ' . $name);
      }
      $this->setHeader($name, $value);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResults() {
    return $this->results;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawResults() {
    return $this->raw_results;
  }

  /**
   * {@inheritdoc}
   */
  public function getLink() {
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function findItemsByProductID(string $keyword) {
    $this->setToggle();
    $this->setOptions([
      'id' => $keyword,
    ]);
    $serviceEndpoint = 'https://' . $this->flipkart_request_root . '/1.0/product.' . $this->response_format;
    $this->prepareLink($serviceEndpoint);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function findItemsByKeywords(string $keyword) {
    $this->setToggle();
    $this->setOptions([
      'query' => $keyword,
      'resultCount' => 10,
    ]);
    $serviceEndpoint = 'https://' . $this->flipkart_request_root . '/1.0/search.' . $this->response_format;
    $this->prepareLink($serviceEndpoint);
    // drupal_set_message(json_encode($this->raw_results), 'status', FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCategoriesUrl() {
    $this->setToggle(FALSE);
    $serviceEndpoint = 'https://' . $this->flipkart_request_root . '/api/' . $this->tracking_id . '.' . $this->response_format;
    $this->prepareLink($serviceEndpoint);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProducts($product_url) {
    $this->setToggle();
    $this->prepareLink($product_url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setToggle($toggle = TRUE) {
    $this->toggle = $toggle;
  }

  /**
   * {@inheritdoc}
   */
  public function cleanResult($raw = []) {
    $data = $this->getRawResults();
    if (count($raw)) {
      $data = $raw;
    }
    if ($this->toggle) {
      $products = [];
      foreach ($data['products'] as $key => $value) {
        try {
          $products[] = $this->buildData($value);
        }
        catch (Exception $e) {
          echo $e->getMessage();
        }
      }
      $this->results = $products;
    } else {
      $categories = [];
      foreach ($data['apiGroups']['affiliate']['apiListings'] as $key => $value) {
        $categories[$key] = $value['availableVariants']['v1.1.0']['get'];
      }
      $this->results = $categories;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetOption(string $name) {
    unset($this->options[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function unsetHeader(string $name) {
    unset($this->headers[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function search(ImmutableConfig $config, string $keyword) {
    $this->setCredentials(
      $config->get('flipkart_tracking_id'),
      $config->get('flipkart_token')
    );
    $response = $this->findItemsByKeywords($keyword)->execute()->getResults();
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function buildData($product_data) {
    $product = [
      'name' => $product_data['productBaseInfoV1']['title'],
      'plugin_id' => 'affiliates_connect_flipkart',
      'product_id' => $product_data['productBaseInfoV1']['productId'],
      'product_description' => $product_data['productBaseInfoV1']['productDescription'],
      'image_urls' => $product_data['productBaseInfoV1']['imageUrls']['400x400'],
      'product_family' => $product_data['productBaseInfoV1']['categoryPath'],
      'currency' => $this->getCurrency($product_data['productBaseInfoV1']['maximumRetailPrice']['currency']),
      'maximum_retail_price' => $product_data['productBaseInfoV1']['maximumRetailPrice']['amount'],
      'vendor_selling_price' => $product_data['productBaseInfoV1']['flipkartSellingPrice']['amount'],
      'vendor_special_price' => $product_data['productBaseInfoV1']['flipkartSpecialPrice']['amount'],
      'product_url' => $product_data['productBaseInfoV1']['productUrl'],
      'product_brand' => $product_data['productBaseInfoV1']['productBrand'],
      'in_stock' => $product_data['productBaseInfoV1']['inStock'],
      'cod_available' => $product_data['productBaseInfoV1']['codAvailable'],
      'discount_percentage' => $product_data['productBaseInfoV1']['discountPercentage'],
      'offers' => $product_data['productBaseInfoV1']['offers'],
      'size' => $product_data['productBaseInfoV1']['attributes']['size'],
      'color' => $product_data['productBaseInfoV1']['attributes']['color'],
      'seller_name' => $product_data['productShippingInfoV1']['sellerName'],
      'seller_average_rating' => $product_data['productShippingInfoV1']['sellerAverageRating'],
      'product_warranty' => '',
      'additional_data' => '',
    ];
    return $product;
  }

  /**
  * Return currency symbol of $CurrencyCode
  * @param  string $CurrencyCode Currency Code
  * @return string Currency Symbol
  */
  public function getCurrency($CurrencyCode) {
    switch($CurrencyCode) {
      case 'INR' :
        return '₹';
      break;
    }
    return '';
  }
}
