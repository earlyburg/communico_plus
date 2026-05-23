<?php

namespace Drupal\communico_plus\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileRepositoryInterface;

/**
 * The UtilityService service class.
 */
class UtilityService {

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * THe file repository interface.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The UtilityService constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory interface.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository interface.
   */
  public function __construct(
    ConfigFactoryInterface $config,
    LoggerChannelFactory $logger_factory,
    DateFormatterInterface $date_formatter,
    FileSystemInterface $file_system,
    ImageFactory $image_factory,
    EntityTypeManagerInterface $entity_manager,
    Connection $connection,
    FileRepositoryInterface $file_repository,
  ) {
    $this->config = $config;
    $this->loggerFactory = $logger_factory;
    $this->dateFormatter = $date_formatter;
    $this->fileSystem = $file_system;
    $this->imageFactory = $image_factory;
    $this->entityTypeManager = $entity_manager;
    $this->database = $connection;
    $this->fileRepository = $file_repository;
  }

  /**
   * The formatDatestamp function.
   *
   * @param string $dateString
   *   The date string to format.
   *
   * @return string
   *   Format a datestamp from Communico into a more readable format.
   */
  public function formatDatestamp($dateString) {
    $type = 'medium';
    $dateObject = new DrupalDateTime($dateString);
    $timestamp = $dateObject->getTimestamp();
    $formatted = $this->dateFormatter->format($timestamp, $type, '');
    return substr($formatted, 0, strpos($formatted, " -"));
  }

  /**
   * The checkIfOneday function.
   *
   * @param string $startDate
   *   The start date of an event.
   * @param string $endDate
   *   The end date of an event.
   *
   * @return false|string
   *   Check if an event is an all day event.
   */
  public function checkIfOneday($startDate, $endDate) {
    $period = FALSE;
    $startString = substr($startDate, -8);
    $endString = substr($endDate, -8);
    if ($startString == '00:00:00' && $endString == '23:59:00') {
      $period = 'All day';
    }
    return $period;
  }

  /**
   * The findHoursFromDatestring function.
   *
   * @param string $dateString
   *   The date string to extract the hours from.
   *
   * @return string
   *   Extract the hours from a datestamp string from Communico.
   */
  public function findHoursFromDatestring($dateString) {
    $time = new DrupalDateTime($dateString);
    return $time->format('g:i A');
  }

  /**
   * The findDateFromDatestring function.
   *
   * @param string $dateString
   *   The date string to extract the date from.
   *
   * @return string
   *   Extract the date from a datestamp string from Communico.
   */
  public function findDateFromDatestring($dateString) {
    $time = new DrupalDateTime($dateString);
    return $time->format('Y-m-d');
  }

  /**
   * The findTimeFromDatestring function.
   *
   * @param string $dateString
   *   The date string to extract the time from.
   *
   * @return string
   *   Extract the time from a datestamp string from Communico.
   */
  public function findTimeFromDatestring($dateString) {
    $time = new DrupalDateTime($dateString);
    return $time->format('g:i A');
  }

  /**
   * The createNodeImage function.
   *
   * @param string $urlPath
   *   A url path.
   * @param string $eventId
   *   An event ID string.
   *
   * @return obj
   *   Returns the image object.
   */
  public function createNodeImage($urlPath, $eventId) {
    $imageFile = FALSE;
    $path = $this->fileSystem->realpath('.') . '/' . PublicStream::basePath() . '/event_images';
    if (!$this->fileSystem->prepareDirectory($path)) {
      $this->fileSystem->mkdir($path);
    }
    $ext = pathinfo($urlPath, PATHINFO_EXTENSION);
    if ($ext != NULL && $ext != '') {
      $fileOb = file_get_contents($urlPath);
      $imageFile = $this->fileRepository->writeData(
        $fileOb,
        'public://event_images/',
        FileSystemInterface::EXISTS_REPLACE
      );

    }
    return $imageFile;
  }

  /**
   * The checkIsEventExpired function.
   *
   * @param string $eventEndDate
   *   The end date of the event.
   *
   * @return bool
   *   Returns TRUE if the event is expired, FALSE otherwise.
   */
  public function checkIsEventExpired($eventEndDate) {
    $date = date('Y-m-d');
    $today_dt = new DrupalDateTime($date);
    $expire_dt = new DrupalDateTime($eventEndDate);
    ($expire_dt < $today_dt) ? $return = TRUE : $return = FALSE;
    return $return;
  }

  /**
   * The getLocationNameFromId function.
   *
   * @param string $locationId
   *   The ID of the location to evaluate.
   *
   * @return false|string
   *   Returns the location string or FALSE.
   *
   * @throws \Exception
   */
  public function getLocationNameFromId($locationId) {
    $locationString = $this->database->select('communico_locations', 'n')
      ->fields('n', ['location_name'])
      ->condition('n.location_id', $locationId, '=')
      ->execute()
      ->fetchField();
    return ($locationString) ? $locationString : FALSE;
  }

  /**
   * The locationDropdown function.
   *
   * @return array
   *   Creates a library locations dropdown array.
   */
  public function locationDropdown() {
    $dropdownArray = [];
    $return = $this->database->select('communico_locations', 'n')
      ->fields('n', ['location_id', 'location_name'])
      ->orderBy('location_name')
      ->execute()
      ->fetchAll();
    foreach ($return as $object) {
      $dropdownArray[$object->location_id] = $object->location_name;
    }
    return $dropdownArray;
  }

  /**
   * The typesDropdown function.
   *
   * @return array
   *   Creates an event types dropdown array.
   *
   * @throws \Exception
   */
  public function typesDropdown() {
    $dropdownArray = [];
    $return = $this->database->select('communico_types', 'n')
      ->fields('n', ['number', 'descr'])
      ->orderBy('descr')
      ->execute()
      ->fetchAll();
    foreach ($return as $object) {
      $dropdownArray[$object->number] = $object->descr;
    }
    return $dropdownArray;
  }

  /**
   * The agesDropdown function.
   *
   * @return array
   *   Creates an event ages dropdown array.
   *
   * @throws \Exception
   */
  public function agesDropdown() {
    $dropdownArray = [];
    $return = $this->database->select('communico_ages', 'n')
      ->fields('n', ['groupname'])
      ->execute()
      ->fetchAll();
    foreach ($return as $object) {
      $dropdownArray[$object->groupname] = $object->groupname;
    }
    return $dropdownArray;
  }

  /**
   * The imageStylesDropdown function.
   *
   * @return array
   *   Creates an image styles dropdown array.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function imageStylesDropdown() {
    $dropdownArray = [];
    $imageStyleStorage = $this->entityTypeManager->getStorage('image_style');
    $styleObjectArray = $imageStyleStorage->loadMultiple();
    foreach ($styleObjectArray as $key => $value) {
      $dropdownArray[$key] = $value->get('label');
    }
    return $dropdownArray;
  }

  /**
   * The checkEventExists function.
   *
   * @param string $eventId
   *   The ID of the event to check for existence.
   *
   * @return false|mixed
   *   Returns TRUE if the event exists, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function checkEventExists($eventId) {
    $idString = $this->database->select('node__field_communico_event_id', 'n')
      ->fields('n', ['field_communico_event_id_value'])
      ->condition('n.field_communico_event_id_value', $eventId, '=')
      ->execute()
      ->fetchField();
    ($idString) ? $return = TRUE : $return = FALSE;
    return $return;
  }

  /**
   * The checkLocationExists function.
   *
   * @param string $locationId
   *   The ID of the location to check for existence.
   *
   * @return bool
   *   Returns TRUE if the location exists, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function checkLocationExists($locationId) {
    $idString = $this->database->select('node__field_communico_location_id', 'n')
      ->fields('n', ['field_communico_location_id_value'])
      ->condition('n.field_communico_location_id_value', $locationId, '=')
      ->execute()
      ->fetchField();
    ($idString) ? $return = TRUE : $return = FALSE;
    return $return;
  }

  /**
   * The getStoredLibraryLocations function.
   *
   * @return array
   *   Returns an array of library locations.
   *
   * @throws \Exception
   */
  public function getStoredLibraryLocations() {
    $returnArray = [];
    foreach ($this->locationDropdown() as $locationId => $nameString) {
      if ($this->checkLocationExists($locationId)) {
        $returnArray[] = $nameString;
      }
    }
    return $returnArray;
  }

  /**
   * The getEventTypeString function.
   *
   * @param string $id
   *   The ID of the event type to retrieve the string for.
   *
   * @return false|mixed
   *   Returns the string associated with an event type ID.
   *
   * @throws \Exception
   */
  public function getEventTypeString($id = NULL) {
    $return = $this->database->select('communico_types', 'n')
      ->fields('n', ['descr'])
      ->condition('n.number', $id, '=')
      ->execute()
      ->fetchField();
    return $return;
  }

  /**
   * The datesDropdown function.
   *
   * @return string[]
   *   Creates a dates dropdown array.
   */
  public function datesDropdown() {
    $timeSelects = [
      'today' => 'Today',
      'tomorrow' => 'Tomorrow',
      'thisweek' => 'This Week',
      'nextweek' => 'Next Week',
      'nextmonth' => 'Next Month',
    ];
    return $timeSelects;
  }

  /**
   * The getDayName function.
   *
   * @param string $daynumber
   *   The number of the day.
   *
   * @return string
   *   Get the name of the day of the week from a number (1-7).
   */
  public function getDayName($daynumber) {
    switch ($daynumber) {
      case $daynumber == '1':
        $dayname = 'Monday';
        break;

      case $daynumber == '2':
        $dayname = 'Tuesday';
        break;

      case $daynumber == '3':
        $dayname = 'Wednesday';
        break;

      case $daynumber == '4':
        $dayname = 'Thursday';
        break;

      case $daynumber == '5':
        $dayname = 'Friday';
        break;

      case $daynumber == '6':
        $dayname = 'Saturday';
        break;

      case $daynumber == '7':
        $dayname = 'Sunday';
        break;
    }
    return $dayname;
  }

  /**
   * The makeAllAgesString function.
   *
   * @return string
   *   Returns a string of all age groups separated by commas.
   *
   * @throws \Exception
   */
  public function makeAllAgesString() {
    $newAgeString = '';
    $dropdownArray = $this->agesDropdown();
    foreach ($dropdownArray as $age) {
      if ($age != 'All ages') {
        $newAgeString .= $age . ',';
      }
    }
    return substr($newAgeString, 0, -1);
  }

  /**
   * The makeAllLocationsString function.
   *
   * @return string
   *   Returns a string of all library locations separated by commas.
   *
   * @throws \Exception
   */
  public function makeAllLocationsString() {
    $dropdownArray = $this->locationDropdown();
    $newLocationString = '';
    foreach ($dropdownArray as $key => $value) {
      $newLocationString .= $key . ',';
    }
    return substr($newLocationString, 0, -1);
  }

  /**
   * The createEventNode function.
   *
   * @param array $valArray
   *   The values for the event node.
   *
   * @return bool
   *   Returns TRUE if the event node is created successfully, FALSE otherwise.
   *
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function createEventNode($valArray) {

    $newEventPage = $this->entityTypeManager->getStorage('node')->create(['type' => 'event_page']);
    $nodeImage = $this->createNodeImage($valArray['eventImage'], $valArray['eventId']);

    $start_date = $this->findDateFromDatestring($valArray['eventStart']);
    $end_date = $this->findDateFromDatestring($valArray['eventEnd']);
    $agesArray = [];
    $typesArray = [];
    foreach ($valArray['ages'] as $age) {
      $agesArray['value'] = $age;
    }
    foreach ($valArray['types'] as $type) {
      $typesArray['value'] = $type;
    }
    $newEventPage->set('title', $valArray['title']);
    $newEventPage->set('field_communico_subtitle', ['value' => $valArray['subTitle']]);
    $newEventPage->set('field_communico_shortdescription', ['value' => $valArray['shortDescription']]);
    $newEventPage->set('body', ['value' => $valArray['description'], 'format' => 'basic_html']);
    $newEventPage->set('field_communico_age_group', $agesArray);
    $newEventPage->set('field_communico_event_id', ['value' => $valArray['eventId']]);
    $newEventPage->set('field_communico_event_type', $typesArray);
    $newEventPage->set('field_communico_start_date', ['value' => $start_date]);
    $newEventPage->set('field_communico_end_date', ['value' => $end_date]);
    $newEventPage->set('field_communico_library_location', ['value' => $valArray['locationName']]);
    $newEventPage->set('field_communico_location_id', ['value' => $valArray['locationId']]);
    $newEventPage->set('field_communico_registration_url', ['value' => $valArray['eventRegistrationUrl']]);
    if ($nodeImage) {
      $ext = pathinfo($valArray['eventImage'], PATHINFO_EXTENSION);
      $newEventPage->set('field_communico_event_image', [
        'target_id' => $nodeImage->id(),
        'alt' => 'A thumbnail image from the API',
        'title' => $valArray['eventId'] . '.' . $ext,
      ]);
    }
    $newEventPage->enforceIsNew();
    $newEventPage->save();
    return TRUE;
  }

  /**
   * The buildDropdownTables function.
   *
   * Builds the dropdown tables for event types, locations, and age groups.
   */
  public function buildDropdownTables() {

    $this->database->truncate('communico_types')->execute();
    $this->database->truncate('communico_locations')->execute();
    $this->database->truncate('communico_ages')->execute();
    $typesArray = communico_plus_get_types_array();
    foreach ($typesArray as $index => $value) {
      $entry = [
        'number' => $index,
        'descr' => $value,
      ];
      $this->database->insert('communico_types')->fields($entry)->execute();
    }
    $locationArray = communico_plus_get_location_array();
    foreach ($locationArray as $index => $value) {
      $entry = [
        'location_id' => $index,
        'location_name' => $value,
      ];
      $this->database->insert('communico_locations')->fields($entry)->execute();
    }
    $agegroupArray = communico_plus_get_agegroups_array();
    foreach ($agegroupArray as $index => $value) {
      $entry = [
        'number' => $index,
        'groupname' => $value,
      ];
      $this->database->insert('communico_ages')->fields($entry)->execute();
    }
  }

}
