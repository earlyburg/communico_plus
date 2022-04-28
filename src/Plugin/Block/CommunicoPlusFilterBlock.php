<?php

namespace Drupal\communico_plus\Plugin\Block;

use Drupal;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Provides a Communico Filter Block.
 *
 * @Block(
 *   id = "communico_plus_filter_block",
 *   admin_label = @Translation("Communico Plus Filter Block"),
 * )
 */
class CommunicoPlusFilterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $build = [
      '#theme' => 'communico_plus_filter_block',
      '#events' => $this->buildCommunicoPlusFilterBlock($config),
    ];
    $build['#cache']['max-age'] = 0;
    return $build;
  }

  /**
   * @return int
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['communico_plus_filter_block_limit'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('Limit the number of results returned'),
      '#default_value' => isset($config['communico_plus_filter_block_limit']) ? $config['communico_plus_filter_block_limit'] : '10',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['communico_plus_filter_block_limit'] = $form_state->getValue('communico_plus_filter_block_limit');
  }

  /**
   * Build the communico_plus event details block
   * @param  array $config
   * @return array
   */
  public function buildCommunicoPlusFilterBlock($config) {
    $filterForm = Drupal::formBuilder()->getForm('Drupal\communico_plus\Form\CommunicoPlusFilterForm');
    $rendered_events['#filter_form'] = [$filterForm];
    return $rendered_events;
  }




}
