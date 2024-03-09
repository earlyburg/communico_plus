<?php

namespace Drupal\communico_plus\Plugin\QueueWorker;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\communico_plus\Service\UtilityService;
use Drupal\communico_plus\Service\ConnectorService;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Communico Plus event sync queue.
 *
 * @QueueWorker(
 *   id = "communico_event_sync_queue",
 *   title = @Translation("Communico Plus Event Sync Queue"),
 *   cron = {"time" = 60}
 * )
 */
class CommunicoEventSyncQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Communico connector service.
   *
   * @var ConnectorService
   */
  protected ConnectorService $connector;

  /**
   * The communico plus utility service.
   *
   * @var UtilityService
   */
  protected UtilityService $utilityService;

  /**
   * The mars market type sync queue constructor.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   The plugin id string.
   * @param string $plugin_definition
   *   The plugin definition string.
   * @param UtilityService $utility_service
   *   The communico utility service.
   * @param ConnectorService $communico_plus_connector
   *   The communico connector service.
   */
  public function __construct(
  array $configuration,
  $plugin_id,
  $plugin_definition,
  UtilityService $utility_service,
  ConnectorService $communico_plus_connector) {
    $this->utilityService = $utility_service;
    $this->connector = $communico_plus_connector;
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
      $container->get('communico_plus.utilities'),
      $container->get('communico_plus.connector'),
    );
  }

  /**
   * @param $item
   * @return void
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws EntityStorageException
   * @throws GuzzleException
   */
  public function processItem($item) {
    if ($item) {
      $events = $this->connector->getEventsFeed($item->startDate, $item->endDate, NULL, NULL, $item->locationId, 500);
      foreach ($events as $eventArray) {
        // If this event does not already exist, create a new event node.
        if (!$this->utilityService->checkEventExists($eventArray['eventId'])) {
          $this->utilityService->createEventNode($eventArray);
        }
      }

    }
  }

}
