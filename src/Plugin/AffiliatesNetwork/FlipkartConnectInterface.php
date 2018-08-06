<?php

namespace Drupal\affiliates_connect_flipkart\Plugin\AffiliatesNetwork;

use Drupal\affiliates_connect\AffiliatesNetworkInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Provides an interface for Affiliates network plugins.
 */
interface FlipkartConnectInterface extends AffiliatesNetworkInterface {

  /**
   * Prepares the API link
   * @param  string $endpoint endpoint to send request
   * @return null
   */
  public function prepareLink(string $endpoint);




  /**
   * Executes the associated operation.
   *
   * @return Flipkartonnect
   */
  public function execute();

  /**
   * @param string $name
   *   The name of the option to set.
   * @param string $value
   *   The value for that option.
   *
   * @return Flipkartonnect
   */
  public function setOption($name, $value);

  /**
   * @param array $options
   *   Options in the form of (string) optionName => (string) optionValue.
   *
   * @return Flipkartonnect
   */
  public function setOptions(array $options);

  /**
   * @param string $name
   *   The name of the header to set.
   * @param string $value
   *   The value for that header.
   *
   * @return Flipkartonnect
   */
  public function setHeader($name, $value);

  /**
   * @param array $Headers
   *   Headers in the form of (string) HeaderName => (string) HeaderValue.
   *
   * @return Flipkartonnect
   */
  public function setHeaders(array $headers);

  /**
   * Returns the result of an Flipkart request.
   *
   * @return array
   */
  public function getResults();

  /**
   * Return the raw result of the Flipkart request
   * @return array
   */
  public function getRawResults();

  /**
   * Return the API link.
   *
   * @return FlipkartConnect url
   */
  public function getLink();

  /**
   * Finds the product using Flipkart Product ID
   * @param  string $keyword Product ID
   * @return FlipkartConnect
   */
  public function findItemsByProductID(string $keyword);

  /**
   * Finds the item/product by keywords.
   * @param  string $keyword keyword to search any item
   * @return Flipkartonnect
   */
  public function findItemsByKeywords(string $keyword);

  /**
   * Fetching categories from the Category API.
   * @return array
   *   A collection of categories along with category url
   */
  public function getCategoriesUrl();

  /**
   * Collect products data from Flipkart Product APIs.
   *
   * @param string $product_url
   *   An url where request is to be made
   */
  public function getProducts($product_url);

  /**
   * Toggle between categories and products for cleansing.
   * @param boolean $toggle
   */
  public function setToggle($toggle = TRUE);

  /**
   * Returns the Clean formateed data.
   *
   * @param array $raw Flipkart APIs response.
   *
   * @return FlipkartConnect
   */
  public function cleanResult($raw = []);

  /**
   * Unset the elements from options array.
   *
   * @param string $name Name of the key.
   */
  public function unsetOption(string $name);

  /**
   * Unset the elements from headers array.
   *
   * @param string $name Name of the key.
   */
  public function unsetHeader(string $name);


  /**
   * Search functionality
   *  @param ImmutableConfig $config
   *  configuration of the plugin
   * @param  string $keyword
   *  keyword to be searched
   * @return array
   *  Array of products
   */
  public function search(ImmutableConfig $config, string $keyword);

  /**
   * To create a product array with appropriate key-value pair.
   * @param array $product_data
   * @return array
   */
  public function buildData($product_data);

}
