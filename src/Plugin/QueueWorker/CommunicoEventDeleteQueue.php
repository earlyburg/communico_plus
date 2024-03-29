<?php

namespace Drupal\communico_plus\Plugin\QueueWorker;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Communico Plus event delete queue.
 *
 * @QueueWorker(
 *   id = "communico_event_delete_queue",
 *   title = @Translation("Communico Plus Event Delete Queue"),
 *   cron = {"time" = 60}
 * )
 */
class CommunicoEventDeleteQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The mars market type sync queue constructor.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   The plugin id string.
   * @param string $plugin_definition
   *   The plugin definition string.
   * @param EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(
  array $configuration,
  $plugin_id,
  $plugin_definition,
  EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * The mars market type sync queue create method.
   *
   * @param ContainerInterface $container
   *   The Symphony container interface.
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   The plugin id string.
   * @param string $plugin_definition
   *   The plugin definition string.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * @param $item
   * @return void
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws EntityStorageException
   *
   * @returns void
   */
  public function processItem($item) {
    if ($item) {
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      $node = $nodeStorage->load($item->id);
      if ($node->id()) {
        $node->delete();
      }
    }
  }

}
