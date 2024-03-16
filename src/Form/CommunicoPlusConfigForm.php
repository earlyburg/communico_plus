<?php

/**
 * @file
 * Contains \Drupal\communico_plus\Form\CommunicoPlusConfigForm.
 */

namespace Drupal\communico_plus\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\communico_plus\Service\UtilityService;

class CommunicoPlusConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const COMMUNICO_PLUS_SETTINGS = 'communico_plus.settings';

  /**
   * @var UtilityService
   */
private UtilityService $utilityService;


  /**
   * @param UtilityService $utility_service
   */
  public function __construct(UtilityService $utility_service) {
    $this->utilityService = $utility_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('communico_plus.utilities')
    );
  }

  /**
   * @return string
   */
  public function getFormId() {
    return 'communico_plus_config_form';
  }

  /**
   * @return string[]
   *
   */
  protected function getEditableConfigNames() {
    return [
      static::COMMUNICO_PLUS_SETTINGS,
    ];
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::COMMUNICO_PLUS_SETTINGS);
    $form['api'] = [
      '#type' => 'details',
      '#title' => $this
        ->t('API'),
      '#open' => TRUE,
    ];

    $form['api']['access_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Access Key'),
      '#default_value' => $config->get('access_key'),
      '#required' => TRUE,
    );

    $form['api']['secret_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#default_value' => $config->get('secret_key'),
      '#required' => TRUE,
    );

    $form['api']['url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Communico API URL'),
      '#default_value' => $config->get('url'),
      '#required' => TRUE,
    );

    $form['api']['linkurl'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Communico Public URL'),
      '#default_value' => $config->get('linkurl'),
      '#required' => TRUE,
    );

    $valid = $config->get('secret_key');
    if($valid != NULL &&  $valid != '') {
      $form['api']['rebuild_drops'] = [
        '#type' => 'checkbox',
        '#title' => 'Rebuild the filter block select element values:',
        '#default_value' => $form_state->getValue('rebuild_drops'),
      ];
    }

    $form['ux'] = [
      '#type' => 'details',
      '#title' => $this
        ->t('User Interface'),
      '#open' => TRUE,
    ];

    $form['ux']['display_calendar'] = [
      '#type' => 'checkbox',
      '#title' => 'Display the option to select a calendar view of events.',
      '#default_value' => $config->get('display_calendar'),
    ];

    $form['ux']['image_styles'] = [
      '#type' => 'select',
      '#title' => 'Choose the image style for the block image display:',
      '#options' => $this->utilityService->imageStylesDropdown(),
      '#empty_option' => $this->t('Image Style'),
      '#default_value' => $config->get('image_styles'),
    ];

    $form['ux']['page_styles'] = [
      '#type' => 'select',
      '#title' => 'Choose the image style for the event page image display:',
      '#options' => $this->utilityService->imageStylesDropdown(),
      '#empty_option' => $this->t('Event Page Image Style'),
      '#default_value' => $config->get('page_styles'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @throws Exception
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if($form_state->getValue('rebuild_drops') == '1') {
      $this->utilityService->buildDropdownTables();
    }
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::COMMUNICO_PLUS_SETTINGS)
      ->set('access_key', $form_state->getValue('access_key'))
      ->set('secret_key', $form_state->getValue('secret_key'))
      ->set('url', $form_state->getValue('url'))
      ->set('linkurl', $form_state->getValue('linkurl'))
      ->set('display_calendar', $form_state->getValue('display_calendar'))
      ->set('image_styles', $form_state->getValue('image_styles'))
      ->set('page_styles', $form_state->getValue('page_styles'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
