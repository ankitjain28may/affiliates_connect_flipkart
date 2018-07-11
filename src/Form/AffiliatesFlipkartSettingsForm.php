<?php

namespace Drupal\affiliates_connect_flipkart\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\affiliates_connect\Form\AffiliatesConnectSettingsForm;
use Drupal\affiliates_connect_flipkart\Controller\FlipkartNativeController;
use Drupal\affiliates_connect_flipkart\FlipkartCategories;

/**
 * Class AffiliatesFlipkartSettingsForm.
 */
class AffiliatesFlipkartSettingsForm extends AffiliatesConnectSettingsForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'affiliates_connect_flipkart_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return array_merge(
      parent::getEditableConfigNames(),
      ['affiliates_connect_flipkart.settings']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('affiliates_connect_flipkart.settings');

    $form['flipkart_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Affiliates Connect Flipkart Settings'),
      '#open' => TRUE,
      '#description' => $this->t('If you are not Flipkart Affiliate, Please Sign
      up for Flipkart Affiliate program here: <a href="@affiliate-marketing">@affiliate-marketing</a>',
          ['@affiliate-marketing' => 'https://affiliate.flipkart.com/']),
    ];

    $form['flipkart_settings']['native_api'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Native API'),
      '#description' => $this->t('Enable Affiliate Marketing using affiliate api'),
      '#default_value' => $config->get('native_api'),
    ];

    $form['flipkart_settings']['native_api_form'] = [
      '#type' => 'details',
      '#title' => $this->t('API Token'),
      '#open' => TRUE,
      '#states' => [
        "visible" => [
          "input[name='native_api']" => ["checked" => TRUE],
        ],
      ],
    ];

    $form['flipkart_settings']['native_api_form']['flipkart_tracking_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Affiliate Tracking ID'),
      '#default_value' => $config->get('flipkart_tracking_id'),
      '#size' => 60,
      '#maxlength' => 60,
      '#states' => [
        "required" => [
          "input[name='native_api']" => ["checked" => TRUE],
        ],
      ],
      '#machine_name' => [
        'exists' => [
          $this,
          'exists',
        ],
      ],
    ];

    $form['flipkart_settings']['native_api_form']['flipkart_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token'),
      '#default_value' => $config->get('flipkart_token'),
      '#size' => 60,
      '#maxlength' => 60,
      '#states' => [
        "required" => [
          "input[name='native_api']" => ["checked" => TRUE],
        ],
      ],
      '#machine_name' => [
        'exists' => [
          $this,
          'exists',
        ],
      ],
    ];

    $form['flipkart_settings']['native_api_form']['data_storage'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Data Storage'),
      '#description' => $this->t('Enable to store product data in your site database.'),
      '#default_value' => $config->get('data_storage'),
    ];

    $form['flipkart_settings']['native_api_form']['data_storage_form'] = [
      '#type' => 'details',
      '#title' => $this->t('Plugin Update'),
      '#open' => TRUE,
      '#states' => [
        "visible" => [
          "input[name='data_storage']" => ["checked" => TRUE],
        ],
      ],
    ];

    $form['flipkart_settings']['native_api_form']['data_storage_form']['import'] = [
      '#type' => 'select',
      '#title' => $this->t('Import period for Affiliate API'),
      '#options' => [
        'every_day' => $this->t('Everyday'),
        'every_15_days' => $this->t('Every 15 days'),
        'every_week' => $this->t('Every week'),
        'every_week' => $this->t('Every month'),
      ],
      '#attributes' => ['class' => ['select-bbq-selector']],
      '#empty_option' => 'Select',
      '#default_value' => $config->get('import'),
      '#description' => $this->t('Fields to be updated through Cron'),
    ];

    $form['flipkart_settings']['native_api_form']['data_storage_form']['full_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Full Content'),
      '#default_value' => $config->get('full_content'),
    ];

    $form['flipkart_settings']['native_api_form']['data_storage_form']['available'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Availability'),
      '#default_value' => $config->get('available'),
    ];

    $form['flipkart_settings']['native_api_form']['data_storage_form']['price'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Price'),
      '#default_value' => $config->get('price'),
    ];

    $form['flipkart_settings']['native_api_form']['data_storage_form']['size'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Size'),
      '#default_value' => $config->get('size'),
    ];

    $form['flipkart_settings']['native_api_form']['data_storage_form']['color'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Color'),
      '#default_value' => $config->get('color'),
    ];

    $form['flipkart_settings']['native_api_form']['data_storage_form']['offers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Offers'),
      '#default_value' => $config->get('offers'),
    ];

    $form['flipkart_settings']['native_api_form']['data_storage_form']['categories'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Category'),
      '#options' => FlipkartCategories::getCategories(),
      '#attributes' => ['class' => ['select-bbq-selector']],
      '#empty_option' => 'Select',
      '#description' => $this->t('The field value wont be saved, It is for import now button only. Leave blank to import from all categories'),
    ];


    $form['flipkart_settings']['native_api_form']['data_storage_form']['submit'] = [
      '#type' => 'submit',
      '#name' => 'native_import',
      '#value' => $this->t('Import Now'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('affiliates_connect_flipkart.settings')
      ->set('native_api', $values['native_api'])
      ->set('flipkart_tracking_id', $values['flipkart_tracking_id'])
      ->set('flipkart_token', $values['flipkart_token'])
      ->set('data_storage', $values['data_storage'])
      ->set('import', $values['import'])
      ->set('full_content', $values['full_content'])
      ->set('price', $values['price'])
      ->set('available', $values['available'])
      ->set('size', $values['size'])
      ->set('color', $values['color'])
      ->set('offers', $values['offers'])
      ->save();
    parent::submitForm($form, $form_state);

    $fk_affiliate_id = $values['flipkart_tracking_id'];
    $token = $values['flipkart_token'];
    $button_clicked = $form_state->getTriggeringElement()['#name'];

    if ($button_clicked == 'native_import') {
      $category = [
        'category' => $values['categories']
      ];
      $form_state->setRedirect('affiliates_connect_flipkart.batch_import', $category);
    }
  }

}
