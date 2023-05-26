<?php

namespace Drupal\communico_plus\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Drupal\Core\Url;
use Drupal\communico_plus\Service\ConnectorService;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Image\ImageFactory;
use Psr\Container\NotFoundExceptionInterface;
use Drupal\communico_plus\Service\UtilityService;


class CommunicoPlusController extends ControllerBase {

  /**
   * @var ConnectorService $connector
   */
  protected ConnectorService $connector;

  /**
   * @param ConfigFactoryInterface $config
   */
  protected ConfigFactoryInterface $config;

  /**
   * @param ModuleHandlerInterface $moduleHandler
   *
   */
  protected $moduleHandler;

  /**
   * The file system service.
   *
   * @var FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * @var MessengerInterface $messengerInterface
   */
  protected $messenger;

  /**
   * The date formatter service.
   *
   * @var DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The image factory.
   *
   * @var ImageFactory
   */
  protected ImageFactory $imageFactory;

  /**
   * @var UtilityService $utilityService
   */
  protected UtilityService $utilityService;

  /**
   * Communico Plus Controller constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   * @param ConnectorService $communico_plus_connector
   * @param ModuleHandlerInterface $module_handler
   * @param FileSystemInterface $file_system
   * @param MessengerInterface $messengerInterface
   * @param DateFormatterInterface $date_formatter
   * @param ImageFactory $image_factory
   * @param UtilityService $utility_service
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ConnectorService $communico_plus_connector,
    ModuleHandlerInterface $module_handler,
    FileSystemInterface $file_system,
    MessengerInterface $messengerInterface,
    DateFormatterInterface $date_formatter,
    ImageFactory $image_factory,
    UtilityService $utility_service,) {
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
   * @param ContainerInterface $container
   * @return CommunicoPlusController|static
   * @throws ContainerExceptionInterface
   * @throws NotFoundExceptionInterface
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
   * @param null $eventId
   * @return string[]
   * @TODO 'create a node' option for searchability
   */
  public function event($eventId = NULL) {
    $event = $this->connector->getEvent($eventId);
    ($event['data']['eventImage'] != NULL) ? $imageUrl = $event['data']['eventImage'] : $imageUrl = FALSE;
    $date = date('Y-m-d H:i:s');
    $today_dt = new DrupalDateTime($date);
    $expire_dt = new DrupalDateTime($event['data']['eventEnd']);
    $branchLink = $this->config
        ->get('communico_plus.settings')
        ->get('linkurl').'/event/'.$event['data']['eventId'].'#branch';
    $calendarImagePath = '/'.$this->moduleHandler
        ->getModule('communico_plus')
        ->getPath() . '/images/calendar.png';
    $map_pinImagePath = '/'.$this->moduleHandler
        ->getModule('communico_plus')
        ->getPath() . '/images/map_pin.png';
    $var ='<h1 class="page-title">';
    $var .= $event['data']['title'];
    $var .= '</h1>';
    $var .='<h2 class="node__title">';
    $var .= $event['data']['subTitle'];
    $var .= '</h2>';
    $var .= '<div class="c-feature">';
    $var .= '<div class="c-iconimage"><img src="'.$map_pinImagePath.'"></div>';
    $var .= '<a href = "'.$branchLink.'" target="_new">'.$event['data']['locationName'].'</a>';
    $var .= '</div>';
    $var .= '<br>';
    $var .= '<div class="c-feature">';
    $var .= '<div class="c-iconimage"><img src="'.$calendarImagePath.'"></div>';
    if ($expire_dt < $today_dt) {
      $this->messenger->addWarning('This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($event['data']['eventEnd']));
      $var .= 'This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($event['data']['eventEnd']);
    } else {
      $var .= 'This event starts on '.$this->utilityService->formatDatestamp($event['data']['eventStart']);
    }
    $var .= '</div>';
    $var .= '<br>';
    $var .= '<div class="c-feature">';
    $var .= '<div class="c-title">Age Group:</div> '.implode(', ',$event['data']['ages']);
    $var .= '</div>';
    $var .= '<br>';
    $var .= '<div class="c-feature">';
    $var .= '<div class="c-title">Event Type:</div> '.implode(', ',$event['data']['types']);
    $var .= '</div>';
    $var .= '<br>';

    $registrationUrl = $event['data']['eventRegistrationUrl'];
    if($registrationUrl != NULL || $registrationUrl != '') {
      $regUrl = Url::fromUri($registrationUrl)->toString();
      $var .= '<div class="c-feature">';
      $var .= '<a href="'.$regUrl.'" target="_new">';
      $var .= '<div id="event-sub-button">Register</div>';
      $var .= '</a>';
      $var .= '</div>';
    }
    $var .= '<p>';
    $var .= $event['data']['shortDescription'];
    $var .= '</p>';
    $var .= '<p>';
    $var .= $event['data']['description'];
    $var .= '</p>';
    $return = [
      '#type' => 'markup',
      '#attached' => [
        'library' => [
          'communico_plus/communico_plus.library',
        ],
      ],
      '#markup' => $var,
      'one_image' => $this->utilityService->createEventImage($imageUrl, $eventId),
    ];
    return $return;
  }

  /**
   * @param null $registrationId
   * @return string[]
   *
   */
  public function reservation($registrationId = NULL) {
    $registration = $this->connector->getReservation($registrationId);
    $date = date('Y-m-d H:i:s');
    $today_dt = new DrupalDateTime($date);
    $expire_dt = new DrupalDateTime($registration['data']['eventEnd']);
    $branchLink = $this->config->get('communico_plus.settings')->get('linkurl').'/event/'.$registration['data']['eventId'].'#branch';
    $var ='<h1 class="page-title">';
    $var .= $registration['data']['title'];
    $var .= '</h1>';
    $var .='<h2 class="node__title">';
    $var .= $registration['data']['subTitle'];
    $var .= '</h2>';
    $var .= '<div class="c-feature">';
    $var .= '<a href = "'.$branchLink.'" target="_new">'.$registration['data']['locationName'].'</a>';
    $var .= '</div>';
    $var .= '<div class="c-feature">';
    if ($expire_dt < $today_dt) {
      $this->messenger->addWarning('This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($registration['data']['eventEnd']));
      $var .= 'This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($registration['data']['eventEnd']);
    } else {
      $var .= 'This event starts on '.$this->utilityService->formatDatestamp($registration['data']['eventStart']);
    }
    $var .= '</div>';
    $var .= '<p>';
    $var .= $registration['data']['shortDescription'];
    $var .= '</p>';
    $var .= '<p>';
    $var .= $registration['data']['description'];
    $var .= '</p>';
    $return = [
      '#type' => 'markup',
      '#markup' => $var,
    ];
    return $return;
  }

}
