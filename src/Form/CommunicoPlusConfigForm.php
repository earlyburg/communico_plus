<?php

/**
 * @file
 * Contains \Drupal\communico_plus\Form\CommunicoPlusConfigForm.
 */

namespace Drupal\communico_plus\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CommunicoPlusConfigForm extends ConfigFormBase {

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
      'communico_plus.settings',
    ];
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('communico_plus.settings');

    $form['access_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Access Key'),
      '#default_value' => $config->get('access_key'),
      '#required' => TRUE,
    );

    $form['secret_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#default_value' => $config->get('secret_key'),
      '#required' => TRUE,
    );

    $form['url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Communico API URL'),
      '#default_value' => $config->get('url'),
      '#required' => TRUE,
    );

    $form['linkurl'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Communico Public URL'),
      '#default_value' => $config->get('linkurl'),
      '#required' => TRUE,
    );
    $valid = $config->get('secret_key');
    if($valid != NULL &&  $valid != '') {
      $form['rebuild_drops'] = [
        '#type' => 'checkbox',
        '#title' => 'Rebuild the filter block select element values:',
        '#default_value' => $form_state->getValue('rebuild_drops'),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if($form_state->getValue('rebuild_drops') == '1') {
      communicoPlusBuildDropdownTables();
    }
  }


  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('communico_plus.settings');
    $config->set('access_key', $form_state->getValue('access_key'));
    $config->set('secret_key', $form_state->getValue('secret_key'));
    $config->set('url', $form_state->getValue('url'));
    $config->set('linkurl', $form_state->getValue('linkurl'));
    $config->save();

    parent::submitForm($form, $form_state);
  }


}