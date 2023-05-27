<?php

/**
 * @file
 * Contains \Drupal\communico_plus\Form\CommunicoPlusImportConfigForm.
 */

namespace Drupal\communico_plus\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CommunicoPlusImportConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const COMMUNICO_PLUS_IMPORT_SETTINGS = 'communico_plus.import.settings';

  /**
   * @return string
   */
  public function getFormId() {
    return 'communico_plus_import_config_form';
  }

  /**
   * @return string[]
   *
   */
  protected function getEditableConfigNames() {
    return [
      static::COMMUNICO_PLUS_IMPORT_SETTINGS,
    ];
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::COMMUNICO_PLUS_IMPORT_SETTINGS);

    $form['access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Key'),
      '#default_value' => $config->get('access_key'),
      '#required' => TRUE,
    ];

    $form['admin_library_location'] = [
      '#type' => 'select',
      '#options' => $this->locationDropdown(),
      '#empty_option' => $this->t('Library'),
      '#default_value' => $config->get('admin_library_location'),
    ];






    return parent::buildForm($form, $form_state);
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @throws \Exception
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::COMMUNICO_PLUS_IMPORT_SETTINGS)
      ->set('access_key', $form_state->getValue('access_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
