<?php

namespace Drupal\communico_plus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Communico Filter Block.
 *
 * @Block(
 *   id = "communico_plus_filter_block",
 *   admin_label = @Translation("Communico Plus Filter Block"),
 * )
 */
class CommunicoPlusFilterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Drupal form builder interface.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * CommunicoPlusFilterBlock constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin id string.
   * @param array $plugin_definition
   *   The plugin definition string.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The Drupal form builder interface.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FormBuilderInterface $form_builder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * The create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Symfony container interface.
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin id string.
   * @param array $plugin_definition
   *   The plugin definition array.
   *
   * @return CommunicoPlusFilterBlock|static
   *   The instantiated block object.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  /**
   * The build method.
   *
   * @return array
   *   The render array for the block.
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
   * The getCacheMaxAge method.
   *
   * @return int
   *   Returns zero.
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
    $form['communico_plus_filter_block_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('Limit the number of results returned'),
      '#default_value' => $config['communico_plus_filter_block_limit'] ?? '10',
    ];
    return $form;
  }

  /**
   * The blockSubmit function.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['communico_plus_filter_block_limit'] = $form_state->getValue('communico_plus_filter_block_limit');
  }

  /**
   * The buildCommunicoPlusFilterBlock method.
   *
   * @param array $config
   *   The block configuration array.
   *
   * @return array
   *   The render array for the block content.
   */
  public function buildCommunicoPlusFilterBlock($config) {
    $filterForm = $this->formBuilder->getForm('Drupal\communico_plus\Form\CommunicoPlusFilterForm');
    $rendered_events['#filter_form'] = [$filterForm];
    return $rendered_events;
  }

}
