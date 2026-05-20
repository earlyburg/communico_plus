<?php

namespace Drupal\communico_plus\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\communico_plus\Service\ConnectorService;
use Drupal\communico_plus\Service\UtilityService;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Image\ImageFactory;

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
  protected FileSystemInterface $fileSystem;

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
  protected DateFormatterInterface $dateFormatter;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected ImageFactory $imageFactory;

  /**
   * The utility service.
   *
   * @var \Drupal\communico_plus\Service\UtilityService
   */
  protected UtilityService $utilityService;

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
  ) {
    $this->config = $config_factory;
    $this->connector = $communico_plus_connector;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
    $this->messenger = $messengerInterface;
    $this->dateFormatter = $date_formatter;
    $this->imageFactory = $image_factory;
    $this->utilityService = $utility_service;
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
    );
  }

  /**
   * The event function.
   *
   * @param string $eventId
   *   The event ID.
   *
   * @return array
   *   The render array for the event page.
   */
  public function event($eventId) {
    $event = $this->connector->getEvent($eventId);

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
    if (array_key_exists('eventImage', $event['data']) && $event['data']['eventImage'] != NULL) {
      $imageUrl = $event['data']['eventImage'];
      $displayImage = $this->utilityService->createControllerDisplayImage($imageUrl, $eventId);
    }

    $var = '';
    $expire_dt = '';
    if (array_key_exists('eventEnd', $event['data']) && $event['data']['eventEnd'] != NULL) {
      $expire_dt = new DrupalDateTime($event['data']['eventEnd']);
      if ($this->utilityService->checkIsEventExpired($expire_dt)) {
        $this->messenger->addWarning('This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($event['data']['eventEnd']));
        $var = 'This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($event['data']['eventEnd']);
      }
      else {
        if (array_key_exists('eventStart', $event['data']) && $event['data']['eventStart'] != NULL) {
          $var .= 'This event starts on ' . $this->utilityService->formatDatestamp($event['data']['eventStart']);
        }
      }
    }

    $regUrl = '';
    if (array_key_exists('eventRegistrationUrl', $event['data']) && $event['data']['eventRegistrationUrl'] != NULL) {
      $registrationUrl = $event['data']['eventRegistrationUrl'];
      $regUrl = Url::fromUri($registrationUrl)->toString();
    }

    $description = '';
    if (array_key_exists('description', $event['data']) && $event['data']['description'] != NULL) {
      $description .= '<p>';
      $description .= $event['data']['shortDescription'];
      $description .= '</p>';
      $description .= '<p>';
      $description .= $event['data']['description'];
      $description .= '</p>';
    }

    return [
      '#attached' => [
        'library' => [
          'communico_plus/communico_plus.library',
        ],
      ],
      '#theme' => 'communico_plus_event_page',
      '#event_data' => $event,
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
