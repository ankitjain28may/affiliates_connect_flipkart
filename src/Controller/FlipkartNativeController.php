<?php

namespace Drupal\affiliates_connect_flipkart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\affiliates_connect\Entity\AffiliatesProduct;
use Drupal\affiliates_connect\AffiliatesNetworkManager;
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
    $this->affiliatesNetworkManager = $affiliatesNetworkManager;
    $this->flipkart = $this->affiliatesNetworkManager->createInstance('affiliates_connect_flipkart');
    $this->flipkart->setCredentials(
      $this->config('affiliates_connect_flipkart.settings')->get('flipkart_tracking_id'),
      $this->config('affiliates_connect_flipkart.settings')->get('flipkart_token')
    );
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
    $categories = $this->flipkart->getCategoriesUrl()->execute()->getResults();
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
    $categories = Self::importingProducts($value);
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
   * To fetch first page.
   *
   * @param string $keyword
   * @param string $category
   * @param int $i
   *
   */
  public static function importingProducts($product_url)
  {
    $flipkart = \Drupal::service('plugin.manager.affiliates_network')->createInstance('affiliates_connect_flipkart');
    $config = \Drupal::configFactory()->get('affiliates_connect_flipkart.settings');
    $tracking_id = $config->get('flipkart_tracking_id');
    $token = $config->get('flipkart_token');

    $flipkart->setCredentials($tracking_id, $token);
    $results = $flipkart->getProducts($product_url)->execute()->getResults();

    foreach ($results as $key => $value) {
      try {
        AffiliatesProduct::createOrUpdate($value, $config);
      }
      catch (Exception $e) {
        echo $e->getMessage();
      }
    }
  }
}
