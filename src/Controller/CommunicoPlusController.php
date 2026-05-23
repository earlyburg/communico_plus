<?php

namespace Drupal\communico_plus\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\communico_plus\Service\ConnectorService;
use Drupal\communico_plus\Service\UtilityService;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The CommunicoPlusController class.
 */
class CommunicoPlusController extends ControllerBase {

  /**
   * Communico connector service.
   *
   * @var \Drupal\communico_plus\Service\ConnectorService
   */
  protected ConnectorService $connector;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $config;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The utility service.
   *
   * @var \Drupal\communico_plus\Service\UtilityService
   */
  protected $utilityService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Communico Plus Controller constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\communico_plus\Service\ConnectorService $communico_plus_connector
   *   The Communico connector service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messengerInterface
   *   The messenger service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\communico_plus\Service\UtilityService $utility_service
   *   The utility service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ConnectorService $communico_plus_connector,
    ModuleHandlerInterface $module_handler,
    FileSystemInterface $file_system,
    MessengerInterface $messengerInterface,
    DateFormatterInterface $date_formatter,
    ImageFactory $image_factory,
    UtilityService $utility_service,
    EntityTypeManagerInterface $entity_manager,
  ) {
    $this->config = $config_factory;
    $this->connector = $communico_plus_connector;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
    $this->messenger = $messengerInterface;
    $this->dateFormatter = $date_formatter;
    $this->imageFactory = $image_factory;
    $this->utilityService = $utility_service;
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * The create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   *
   * @return \Drupal\communico_plus\Controller\CommunicoPlusController|static
   *   The instantiated object.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('communico_plus.connector'),
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('messenger'),
      $container->get('date.formatter'),
      $container->get('image.factory'),
      $container->get('communico_plus.utilities'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * The getEventNode function.
   *
   * @param string $eventId
   *   The event ID.
   *
   * @return array
   *   The render array for the event page.
   */
  public function getEventNode($eventId) {
    $returnArray = [];

    $eventStorage = $this->entityTypeManager->getStorage('node');
    $eventNid = $eventStorage->getQuery()
      ->condition('type', 'event_page')
      ->condition('field_communico_event_id', $eventId)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($eventNid)) {
      $key = array_key_first($eventNid);

      $loadedEvent = $eventStorage->load($eventNid[$key]);

      $branchLink = $this->config
        ->get('communico_plus.settings')
        ->get('linkurl') . '/event/' . $eventId . '#branch';

      $calendarImagePath = '/' . $this->moduleHandler
        ->getModule('communico_plus')
        ->getPath() . '/images/calendar.png';

      $map_pinImagePath = '/' . $this->moduleHandler
        ->getModule('communico_plus')
        ->getPath() . '/images/map_pin.png';

      $displayImage = '';
      if ($loadedEvent->get('field_communico_event_image')->title != NULL) {
        $displayImage = $loadedEvent->get('field_communico_event_image')->view();
      }

      $startDate = $loadedEvent->get('field_communico_start_date')->value;
      $endDate = $loadedEvent->get('field_communico_end_date')->value;
      $eventTitle = $loadedEvent->get('title')->value;
      $eventSubTitle = $loadedEvent->get('field_communico_subtitle')->value;
      $eventLocationId = $loadedEvent->get('field_communico_location_id')->value;
      $eventLocationName = $this->utilityService->getLocationNameFromId($eventLocationId);

      $eventAgeGroups = $loadedEvent->get('field_communico_age_group')->getValue();
      $agesArray = [];
      foreach ($eventAgeGroups as $value) {
        $agesArray[] = $value['value'];
      }

      $eventTypes = $loadedEvent->get('field_communico_event_type')->getValue();
      $evemtTypesArray = [];
      foreach ($eventTypes as $value) {
        $evemtTypesArray[] = $value['value'];
      }

      $var = '';
      $expire_dt = '';
      if ($startDate != NULL) {
        $expireDate = new DrupalDateTime($endDate);
        if ($this->utilityService->checkIsEventExpired($expireDate)) {
          $this->messenger->addWarning('This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($endDate));
          $var = 'This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($endDate);
        }
        else {
          if ($startDate != NULL) {
            $var .= 'This event starts on ' . $this->utilityService->formatDatestamp($startDate);
          }
        }
      }

      $regUrl = '';
      if ($loadedEvent->get('field_communico_registration_url')->uri != NULL) {
        $regUrl = $loadedEvent->get('field_communico_registration_url')->uri;
      }

      $description = '';
      if ($loadedEvent->get('body')->value != NULL) {
        $description .= '<p>';
        $description .= $loadedEvent->get('field_communico_shortdescription')->value;
        $description .= '</p>';
        $description .= '<p>';
        $description .= $loadedEvent->get('body')->value;
        $description .= '</p>';
      }

      $returnArray = [
        '#attached' => [
          'library' => [
            'communico_plus/communico_plus.library',
          ],
        ],
        '#theme' => 'communico_plus_event_page',
        '#title' => $eventTitle,
        '#subTitle' => $eventSubTitle,
        '#locationName' => $eventLocationName,
        '#ages' => $agesArray,
        '#types' => $evemtTypesArray,
        '#expire_date' => $expire_dt,
        '#branch_link' => $branchLink,
        '#calendar_image_path' => $calendarImagePath,
        '#map_pin_image_path' => $map_pinImagePath,
        '#reg_url' => $regUrl,
        '#expired_text' => $var,
        '#description' => $description,
        '#one_image' => $displayImage,
      ];
    }
    return $returnArray;
  }

  /**
   * The reservation function.
   *
   * @param string $registrationId
   *   The registration ID.
   *
   * @return array
   *   The render array for the reservation page.
   */
  public function reservation($registrationId) {
    $registration = $this->connector->getReservation($registrationId);

    $branchLink = $this->config
      ->get('communico_plus.settings')
      ->get('linkurl') . '/event/' . $registration['data']['eventId'] . '#branch';

    $var = '';
    $expire_dt = '';
    if (array_key_exists('eventEnd', $registration['data']) && $registration['data']['eventEnd'] != NULL) {
      $expire_dt = new DrupalDateTime($registration['data']['eventEnd']);
      if ($this->utilityService->checkIsEventExpired($expire_dt)) {
        $this->messenger->addWarning('This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($registration['data']['eventEnd']));
        $var = 'This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($registration['data']['eventEnd']);
      }
      else {
        if (array_key_exists('eventStart', $registration['data']) && $registration['data']['eventStart'] != NULL) {
          $var .= 'This event starts on ' . $this->utilityService->formatDatestamp($registration['data']['eventStart']);
        }
      }
    }

    $description = '';
    if (array_key_exists('description', $registration['data']) && $registration['data']['description'] != NULL) {
      $description .= '<p>';
      $description .= $registration['data']['shortDescription'];
      $description .= '</p>';
      $description .= '<p>';
      $description .= $registration['data']['description'];
      $description .= '</p>';
    }

    return [
      '#attached' => [
        'library' => [
          'communico_plus/communico_plus.library',
        ],
      ],
      '#theme' => 'communico_plus_reservation_page',
      '#reservation_data' => $registration,
      '#expire_date' => $expire_dt,
      '#branch_link' => $branchLink,
      '#expired_text' => $var,
      '#description' => $description,
    ];
  }

}
