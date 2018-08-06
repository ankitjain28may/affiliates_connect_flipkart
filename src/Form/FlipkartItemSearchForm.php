<?php

namespace Drupal\affiliates_connect_flipkart\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\affiliates_connect\AffiliatesNetworkManager;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\affiliates_connect\Entity\AffiliatesProduct;

/**
 * Class FlipkartItemSearchForm.
 */
class FlipkartItemSearchForm extends FormBase {

  /**
   * The affiliates network manager.
   *
   * @var \Drupal\affiliates_connect\AffiliatesNetworkManager
   */
  private $affiliatesNetworkManager;

  /**
   * The Flipkart Instance.
   *
   * @var \Drupal\affiliates_connect_flipkart\Plugin\AffiliatesNetwork\FlipkartConnect
   */
  private $flipkart;

  /**
   * The search data
   * @var array|null
   */
  protected $result;


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.affiliates_network'),
      $container->get('user.private_tempstore')
    );
  }

  /**
   * AffiliatesConnectController constructor.
   *
   * @param \Drupal\affiliates_connect\AffiliatesNetworkManager $affiliatesNetworkManager
   *   The affiliates network manager.
   */
  public function __construct(AffiliatesNetworkManager $affiliatesNetworkManager, PrivateTempStoreFactory $temp_store_factory) {
    $this->affiliatesNetworkManager = $affiliatesNetworkManager;
    $this->results = $temp_store_factory->get('flipkart_search');
    $this->flipkart = $this->affiliatesNetworkManager->createInstance('affiliates_connect_flipkart');
    $this->flipkart->setCredentials(
      $this->config('affiliates_connect_flipkart.settings')->get('flipkart_tracking_id'),
      $this->config('affiliates_connect_flipkart.settings')->get('flipkart_token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'affiliates_connect_flipkart_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('affiliates_connect_flipkart.settings');

    $form['container'] = [
      '#type' => 'container',
      '#attributes' => [
          'class' => ['container-inline'],
      ],
    ];

    $form['container']['search_by'] = [
      '#type' => 'select',
      '#options' => $this->buildCategories(),
      '#attributes' => ['class' => ['button']],
      '#empty_option' => 'Search By',
      '#default_value' => $this->results->get('search_by'),
      '#required' => TRUE,
    ];

    $form['container']['keyword'] = [
      '#type' => 'textfield',
      '#default_value' => $this->results->get('keyword'),
      '#size' => 60,
      '#maxlength' => 60,
      '#placeholder' => 'Enter a keyword or ID',
      '#required' => TRUE,
    ];

    $form['container']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Search'),
    ];

    if ($this->results->get('data')) {

      if ($config->get('data_storage')) {

        $form['table'] = [
          '#type' => 'tableselect',
          '#header' => $this->getHeader(),
          '#options' => $this->buildRows(),
          '#multiple' => true,
          '#empty' => $this->t('No products found'),
        ];

        $form['import'] = [
          '#type' => 'submit',
          '#name' => 'import',
          '#button_type' => 'primary',
          '#value' => $this->t('Import'),
        ];
      } else {
        $form['table'] = [
          '#type' => 'table',
          '#header' => $this->getHeader(),
          '#rows' => $this->buildRows(),
          '#multiple' => true,
          '#empty' => $this->t('No products found'),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $keyword = $values['keyword'];
    $search = $values['search_by'];

    if (!$this->config('affiliates_connect_flipkart.settings')->get('native_api')) {
      drupal_set_message($this->t('Configure flipkart native api to import data'), 'error', FALSE);
      $this->results->set('data', '');
      $this->results->set('keyword', '');
      $this->results->set('search_by', '');
      return $this->redirect('affiliates_connect_flipkart.settings');
    }

    $button_clicked = $form_state->getTriggeringElement()['#name'];
    if ($button_clicked == 'import') {
      $selected_names = array_filter($values['table']);
      $this->importProducts($selected_names);
      return;
    } else {
      if ($search == 'keyword') {
        $data = $this->flipkart->findItemsByKeywords($keyword)->execute()->getResults();
      } else {
        $data = $this->flipkart->findItemsByProductID($keyword)->execute()->getResults();
      }
    }
    $this->results->set('data', $data);
    $this->results->set('keyword', $keyword);
    $this->results->set('search_by', $search);

  }

  /**
   * Add the header to the table
   * @return array header fields
   */
  public function getHeader()
  {
    $header = [
     'image' => $this->t('Image'),
     'name' => $this->t('Product Name'),
     'mrp' => $this->t('M.R.P'),
   ];
   return $header;
  }
  /**
   * Build the rows for the table
   * @return array fetched data from the API
   */
  public function buildRows()
  {
    $row = [];
    $data = $this->results->get('data');
    if (!$data) {
      return;
    }
    foreach ($data as $key => $item) {
      $row[$key+1] = [
        'image' => [
          'data' => [
            '#prefix' => '<div><img src="' . $item['image_urls'] . '" width=30 height=40> &nbsp;&nbsp;',
            '#suffix' => '</div>',
          ],
        ],
        'name' => [
          'data' => [
            '#prefix' => '<a href="' . $item['product_url'] . '">' . $item['name'] . '</a>'
          ],
        ],
        'mrp' => ($item['maximum_retail_price']) ? $item['currency'] . $item['maximum_retail_price'] : '-',
      ];
    }
    return $row;
  }

  /**
   * Build the Categories on the basis of locale.
   * @return array
   */
  public function buildCategories() {
    $categories['keyword'] = 'Search by Keyword.';
    $categories['asin'] = 'Search by Product ID.';
    return $categories;
  }

  /**
   * Save data to the Product Entity.
   * @param  array $element Seleted table rows.
   */
  public function importProducts($element)
  {
    $config = \Drupal::configFactory()->get('affiliates_connect_flipkart.settings');
    $data = $this->results->get('data');
    foreach ($element as $value) {
      AffiliatesProduct::createOrUpdate($data[$value - 1], $config);
    }
    drupal_set_message($this->t('Products are imported successfully'), 'status', FALSE);
  }
}
