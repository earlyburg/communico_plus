<?php
/**
 * @file
 * Contains \Drupal\communico_plus\Service\UtilityService.
 */
namespace Drupal\communico_plus\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\StreamWrapper\PublicStream;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Psr\Container\NotFoundExceptionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class UtilityService
 * @package Drupal\communico_plus
 *
 */
class UtilityService {

  /**
   * The config factory interface.
   *
   * @var ConfigFactoryInterface $config
   */
  protected ConfigFactoryInterface $config;

  /**
    * Messenger service.
    *
    * @var LoggerChannelFactory $loggerFactory
    */
 protected LoggerChannelFactory $loggerFactory;

  /**
   * The date formatter service.
   *
   * @var DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The file system service.
   *
   * @var FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The image factory.
   *
   * @var ImageFactory
   */
  protected ImageFactory $imageFactory;

  /**
   * @var Connection $connection
   */
  protected Connection $database;

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface $entity_manager
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @param ConfigFactoryInterface $config
   * @param LoggerChannelFactory $logger_factory
   * @param DateFormatterInterface $date_formatter
   * @param FileSystemInterface $file_system
   * @param ImageFactory $image_factory
   * @param EntityTypeManagerInterface $entity_manager
   * @param Connection $connection
   */
  public function __construct(
    ConfigFactoryInterface $config,
    LoggerChannelFactory $logger_factory,
    DateFormatterInterface $date_formatter,
    FileSystemInterface $file_system,
    ImageFactory $image_factory,
    EntityTypeManagerInterface $entity_manager,
    Connection $connection,) {
    $this->config = $config;
    $this->loggerFactory = $logger_factory;
    $this->dateFormatter = $date_formatter;
    $this->fileSystem = $file_system;
    $this->imageFactory = $image_factory;
    $this->entityTypeManager = $entity_manager;
    $this->database = $connection;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   * @throws ContainerExceptionInterface
   * @throws NotFoundExceptionInterface
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('date.formatter'),
      $container->get('file_system'),
      $container->get('image.factory'),
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  /**
   * @param null $dateString
   * @return string
   * formats a Communico date into a more readable format
   */
  public function formatDatestamp($dateString = NULL) {
    $type = 'medium';
    $dateObject = new DrupalDateTime($dateString);
    $timestamp = $dateObject->getTimestamp();
    $formatted = $this->dateFormatter->format($timestamp, $type, '');
    $cleanDate = substr($formatted, 0, strpos($formatted, " -"));
    return $cleanDate;
  }

  /**
   * @param $startDate
   * @param $endDate
   * @return false|string
   *
   */
  public function checkIfOneday($startDate, $endDate) {
    $period = FALSE;
    $startString = substr($startDate, -8);
    $endString = substr($endDate, -8);
    if($startString == '00:00:00' && $endString == '23:59:00') {
      $period = 'All day';
    }
    return $period;
  }

  /**
   * @param null $dateString
   * @return string
   *
   */
  public function findHoursFromDatestring($dateString = NULL) {
    $time = new DrupalDateTime($dateString);
    $time = $time->format('g:i A');
    return $time;
  }

  /**
   * @param null $dateString
   * @return string
   *
   */
  public function findDateFromDatestring($dateString = NULL) {
    $time = new DrupalDateTime($dateString);
    $date = $time->format('Y-m-d');
    return $date;
  }

  /**
   * @param $imageUrl
   * @param $eventId
   * @return array
   * creates an image render array in drupal for an event
   * @TODO get rid of built up images periodically
   */
  public function createEventImage($imageUrl, $eventId) {
    $image_render_array = FALSE;
    $path = $this->fileSystem->realpath('.') . '/' . PublicStream::basePath().'/event_images';
    if (!$this->fileSystem->prepareDirectory($path)) {
      $this->fileSystem->mkdir($path);
    }
    $ext = pathinfo($imageUrl, PATHINFO_EXTENSION);
    if($ext != NULL && $ext != '') {
      $file_path_physical = $path . '/' . $eventId . '.' . $ext;
      /* check if the image already exists */
      if(file_exists($file_path_physical)) {
        $image = $this->imageFactory->get($file_path_physical);
        if ($image->isValid()) {
          $image_render_array = [
            '#theme' => 'image_style',
            '#width' => $image->getWidth(),
            '#height' => $image->getHeight(),
            '#style_name' => 'medium',
            '#uri' => 'public://event_images/' . $eventId . '.' . $ext,
          ];
        }
      } else {
        /* save to fs */
        $fileOb = file_get_contents($imageUrl);
        $savedFile = $this->fileSystem->saveData($fileOb, $file_path_physical, true);
        $image = $this->imageFactory->get($savedFile);
        if ($image->isValid()) {
          $image_render_array = [
            '#theme' => 'image_style',
            '#width' => $image->getWidth(),
            '#height' => $image->getHeight(),
            '#style_name' => 'medium',
            '#uri' => 'public://event_images/' . $eventId . '.' . $ext,
          ];
        }
      }
    }
    return $image_render_array;
  }

  /**
   * @param $eventEndDate
   * @return bool
   *
   */
  public function checkIsEventExpired($eventEndDate) {
    $date = date('Y-m-d H:i:s');
    $today_dt = new DrupalDateTime($date);
    $expire_dt = new DrupalDateTime($eventEndDate);
    ($expire_dt < $today_dt) ? $return = true : $return = false;
    return $return;
  }

  /**
   * @param $valArray
   * @return true
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createEventPageNode($valArray) {
    $newEventPage = $this->entityTypeManager->getStorage('node')->create(['type' => 'event_page']);
    $newEventPage->set('title', $valArray['title']);
    $newEventPage->set('body', ['value' => $valArray['description'], 'format' => 'basic_html']);
    $newEventPage->set('field_age_group', ['value' => $valArray['ages']]);
    $newEventPage->set('field_event_id', ['value' => $valArray['eventId']]);
    $newEventPage->set('field_event_type', ['value' => $valArray['types']]);
    $newEventPage->set('field_start_date', ['value' => $valArray['eventStart']]);
    $newEventPage->set('field_end_date', ['value' => $valArray['eventEnd']]);
    $newEventPage->enforceIsNew();
    $newEventPage->save();
    return true;
  }

  /**
   * @return array
   * creates a library locations dropdown array
   *
   */
  public function locationDropdown() {
    $dropdownArray = [];
    $return = $this->database->select('communico_locations', 'n')
      ->fields('n', array('location_id', 'location_name'))
      ->orderBy('location_name')
      ->execute()
      ->fetchAll();
    foreach($return as $object) {
      $dropdownArray[$object->location_id] = $object->location_name;
    }
    return $dropdownArray;
  }

  /**
   * @return array
   * creates an event types dropdown array
   *
   */
  public function typesDropdown() {
    $dropdownArray = [];
    $return = $this->database->select('communico_types', 'n')
      ->fields('n', array('number', 'descr'))
      ->orderBy('descr')
      ->execute()
      ->fetchAll();
    foreach($return as $object) {
      $dropdownArray[$object->number] = $object->descr;
    }
    return $dropdownArray;
  }

  /**
   * @return array
   * creates an event types dropdown array
   *
   */
  public function agesDropdown() {
    $dropdownArray = [];
    $return = $this->database->select('communico_ages', 'n')
      ->fields('n', array('groupname'))
      ->execute()
      ->fetchAll();
    foreach($return as $object) {
      $dropdownArray[$object->groupname] = $object->groupname;
    }
    return $dropdownArray;
  }


}
