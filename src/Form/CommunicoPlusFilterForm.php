<?php

namespace Drupal\communico_plus\Form;

use Drupal\communico_plus\Service\ConnectorService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Url;
use Drupal\communico_plus\Service\UtilityService;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Render\RendererInterface;

/**
 * The CommunicoPlusFilterForm class.
 */
class CommunicoPlusFilterForm extends FormBase {

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $config;

  /**
   * The UtilityService object.
   *
   * @var \Drupal\communico_plus\Service\UtilityService
   */
  protected UtilityService $utilityService;

  /**
   * The LoggerChannelFactory object.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The ConnectorService object.
   *
   * @var \Drupal\communico_plus\Service\ConnectorService
   */
  protected ConnectorService $connector;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Drupal\Core\Render\RendererInterface definition.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The page cache kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected KillSwitch $killSwitch;

  /**
   * CommunicoPlusFilterForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The ConfigFactoryInterface object.
   * @param \Drupal\communico_plus\Service\UtilityService $utility_service
   *   The UtilityService object.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The LoggerChannelFactory object.
   * @param \Drupal\communico_plus\Service\ConnectorService $communico_plus_connector
   *   The ConnectorService object.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $kill_switch
   *   The page cache kill switch.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    UtilityService $utility_service,
    LoggerChannelFactory $logger_factory,
    ConnectorService $communico_plus_connector,
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $requestStack,
    ModuleHandlerInterface $module_handler,
    RendererInterface $renderer,
    KillSwitch $kill_switch,
  ) {
    $this->config = $configFactory;
    $this->utilityService = $utility_service;
    $this->loggerFactory = $logger_factory;
    $this->connector = $communico_plus_connector;
    $this->database = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $requestStack;
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
    $this->killSwitch = $kill_switch;
  }

  /**
   * The create method.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   *   The instantiated object.
   *
   * @throws \Psr\Container\ContainerExceptionInterface
   * @throws \Psr\Container\NotFoundExceptionInterface
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('communico_plus.utilities'),
      $container->get('logger.factory'),
      $container->get('communico_plus.connector'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('page_cache_kill_switch'),
    );
  }

  /**
   * The getFormId method.
   *
   * @return string
   *   The form ID string.
   */
  public function getFormId() {
    return 'communico_plus_filter_form';
  }

  /**
   * The buildForm method.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return array
   *   The form object.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config->get('communico_plus.settings');
    if ($config->get('display_calendar') == '1') {
      $form['layout_box'] = [
        '#markup' => '<div id="cp-layout-box">',
      ];

      $form['layout'] = [
        '#type' => 'radios',
        '#options' => [0 => $this->t('Feed'), 1 => $this->t('Calendar')],
        '#default_value' => 0,
        '#ajax' => [
          'event' => 'change',
          'callback' => '::updateCommunicoBlock',
          'wrapper' => 'feed_area_wrapper',
        ],
      ];

      $form['layout_box_end'] = [
        '#markup' => '</div>',
      ];
    }
    $form['row_box'] = [
      '#markup' => '<div id="cp-row-box">',
    ];

    $form['announce'] = [
      '#markup' => '<div class="form-markup">Filter By: </div>',
    ];

    $form['library_location'] = [
      '#type' => 'select',
      '#options' => $this->utilityService->locationDropdown(),
      '#empty_option' => $this->t('Library'),
      '#default_value' => $form_state->getValue('library_location'),
      '#ajax' => [
        'event' => 'change',
        'callback' => '::updateCommunicoBlock',
        'wrapper' => 'feed_area_wrapper',
      ],
    ];

    $form['library_agegroup'] = [
      '#type' => 'select',
      '#options' => $this->utilityService->agesDropdown(),
      '#empty_option' => $this->t('Age Group'),
      '#default_value' => $form_state->getValue('library_agegroup'),
      '#ajax' => [
        'event' => 'change',
        'callback' => '::updateCommunicoBlock',
        'wrapper' => 'feed_area_wrapper',
      ],
    ];

    $form['event_type'] = [
      '#type' => 'select',
      '#options' => $this->utilityService->typesDropdown(),
      '#empty_option' => $this->t('Event Type'),
      '#default_value' => $form_state->getValue('event_type'),
      '#ajax' => [
        'event' => 'change',
        'callback' => '::updateCommunicoBlock',
        'wrapper' => 'feed_area_wrapper',
      ],
    ];

    $form['event_date'] = [
      '#type' => 'select',
      '#options' => $this->utilityService->datesDropdown(),
      '#empty_option' => $this->t('Date'),
      '#default_value' => $form_state->getValue('event_date'),
      '#ajax' => [
        'event' => 'change',
        'callback' => '::updateCommunicoBlock',
        'wrapper' => 'feed_area_wrapper',
      ],
      '#states' => [
        'invisible' => [':input[name="layout"]' => ['value' => 1]],
      ],
    ];

    $form['row_box_end'] = [
      '#markup' => '</div>',
    ];

    $markup = $form_state->getValue('feed_area');
    if ($markup == NULL || $markup == '') {
      $markup = $this->popIfEmpty();
    }

    $form['feed_area'] = [
      '#markup' => $markup,
      '#prefix' => '<div id="feed_area_wrapper">',
      '#suffix' => '</div>',
    ];

    $form['actions']['#type'] = 'actions';

    return $form;
  }

  /**
   * The validateForm method.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * The popIfEmpty method.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->loggerFactory->get('communico_plus')->warning(' submit never happens ');
    $form_state
      ->set('layout', $form_state->getValue('layout'))
      ->set('event_date', $form_state->getValue('event_date'))
      ->set('event_type', $form_state->getValue('event_type'))
      ->set('library_location', $form_state->getValue('library_location'))
      ->set('library_agegroup', $form_state->getValue('library_agegroup'))
      ->setRebuild(TRUE);
  }

  /**
   * The createWall method.
   *
   * @param array $events
   *   The array of events to create the wall from.
   *
   * @return string
   *   The HTML string for the wall.
   *
   * @throws Exception
   */
  public function createWall($events = NULL) {
    $this->killSwitch->trigger();
    $link_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
    $return = '';
    foreach ($events as $event) {
      ($event['eventImage'] != NULL) ? $imageUrl = $event['eventImage'] : $imageUrl = FALSE;
      $imageRenderArray = $this->utilityService->createEventImage($imageUrl, $event['eventId']);
      $full_link = $link_url . '/event/' . $event['eventId'];
      try {
        $url = Url::fromUri(ltrim($full_link));
        $link = Link::fromTextAndUrl($event['title'], $url)->toString();
      }
      catch (InvalidArgumentException $e) {
        $this->loggerFactory->get('communico_plus')->error('Error occurred while creating event link.');
      }
      $startTime = $this->utilityService->findHoursFromDatestring($event['eventStart']);
      $endTime = $this->utilityService->findHoursFromDatestring($event['eventEnd']);
      $expire_dt = new DrupalDateTime($event['eventEnd']);

      $branchLink = $this->config->get('communico_plus.settings')->get('linkurl') . '/event/' . $event['eventId'] . '#branch';
      $map_pinImagePath = '/' . $this->moduleHandler->getModule('communico_plus')->getPath() . '/images/map_pin.png';
      $var = '';
      $var .= '<div id="event-block">';

      $var .= '<div class="block-section">';
      $var .= '<div class="section-time">';

      if ($this->utilityService->checkIsEventExpired($expire_dt)) {
        $var .= 'This event is finished. The event ended on ' . $this->utilityService->formatDatestamp($event['eventEnd']);
      }
      else {
        if ($startTime == '12:00 AM' && $endTime == '11:59 PM') {
          $var .= 'All Day';
        }
        else {
          $var .= $startTime . ' - ' . $endTime;
        }
      }
      $var .= '</div>';
      $var .= '</div>'; /* END .block-section */

      $var .= '<div class="block-section-center">';
      if ($imageRenderArray) {
        $var .= '<div class="section-image">';
        $var .= $this->renderer->render($imageRenderArray);
        $var .= '</div>';
      }
      $var .= '<h2>';
      $var .= $link;
      $var .= '</h2>';
      $var .= '<div class="c-feature">';
      $var .= '<a href = "' . $branchLink . '" target="_new">';
      $var .= '<div class="c-iconimage"><img src="' . $map_pinImagePath . '"></div>';
      $var .= '</a>';
      $var .= $event['locationName'];
      $var .= '</div>';
      $var .= '<br>';
      $var .= '<div class="c-feature">';
      $var .= '<div class="c-title">Age Group:</div> ' . implode(', ', $event['ages']);
      $var .= '</div>';
      $var .= '<br>';
      $var .= '<div class="c-feature">';
      $var .= '<div class="c-title">Event Type:</div> ' . implode(', ', $event['types']);
      $var .= '</div>';
      $var .= '<br>';
      $var .= '<div class="c-feature-paragraph">';
      $var .= '<p>';
      $var .= $event['subTitle'];
      $var .= $event['shortDescription'];
      $var .= '</p>';
      $var .= '</div>';
      $var .= '</div>'; /* END .block-section-center */
      $var .= '<div class="block-section">';
      $registrationUrl = $event['eventRegistrationUrl'];
      if ($registrationUrl != NULL || $registrationUrl != '') {
        try {
          $regUrl = Url::fromUri(ltrim($registrationUrl));
          $var .= '<div class="c-feature">';
          $var .= '<a href="' . $regUrl->toUriString() . '" target="_new">';
          $var .= '<div id="event-sub-button">Register</div>';
          $var .= '</a>';
          $var .= '</div>';
        }
        catch (InvalidArgumentException $e) {
          $this->loggerFactory->get('communico_plus')->error('Error occurred while creating registration link.');
        }
      }
      $var .= '</div>'; /* END .block-section */
      $var .= '</div>'; /* END #event-block */
      $return .= $var;
    }
    return $return;
  }

  /**
   * The getEventTextKeys method.
   *
   * @param string $date
   *   A string representing the date.
   * @param array $array
   *   An array of events to search through.
   *
   * @return array
   *   An array of events matching the date provided.
   */
  public function getEventTextKeys($date, $array) {
    $keyArray = [];
    foreach ($array as $key => $val) {
      if ($val['eventDate'] == $date) {
        $keyArray[$key] = $val;
      }
    }
    return $keyArray;
  }

  /**
   * The createCalendarEventListings method.
   *
   * @param array $datedArray
   *   An array of events for a given date.
   *
   * @return string
   *   The HTML string for the calendar event listings for a given date.
   */
  public function createCalendarEventListings($datedArray) {
    $returnText = '';
    $link_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
    foreach ($datedArray as $value) {
      $full_link = $link_url . '/event/' . $value['eventId'];
      $url = Url::fromUri($full_link);
      $link = Link::fromTextAndUrl($value['title'], $url)->toString();
      if ($value['allDay'] == '1') {
        $returnText .= '<div class="cal-text">All day.</div>';
      }
      else {
        $returnText .= '<div class="cal-text">' . $value['eventStart'] . ' - ' . $value['eventEnd'] . '</div>';
      }
      $returnText .= '<div class="cal-title">' . $link . '</div>';
      $returnText .= '<br>';
    }
    return $returnText;
  }

  /**
   * The createCalendar method.
   *
   * @param array $events
   *   An array of events to be used in the calendar.
   * @param false $current_date
   *   A string representing the current date.
   *
   * @return string
   *   An HTML string representing the calendar view of events.
   */
  public function createCalendar($events = NULL, $current_date = FALSE) {
    $singleNumArray = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $eventsArray = [];
    foreach ($events as $event) {
      $eventsArray[] = [
        'eventDate' => $this->utilityService->findDateFromDatestring($event['eventStart']),
        'eventStart' => $this->utilityService->findHoursFromDatestring($event['eventStart']),
        'eventEnd' => $this->utilityService->findHoursFromDatestring($event['eventEnd']),
        'allDay' => ($this->utilityService->checkIfOneday($event['eventStart'], $event['eventEnd'])) ? '1' : '0',
        'title' => $event['title'],
        'eventId' => $event['eventId'],
        'locationId' => $event['locationId'],
      ];
    }
    ($current_date) ? $currentDate = $current_date : $currentDate = date('Y-m-d');
    $parts = explode('-', $currentDate);
    $yearArray = [
      '01' => '31',
      '02' => '28',
      '03' => '31',
      '04' => '30',
      '05' => '31',
      '06' => '30',
      '07' => '31',
      '08' => '31',
      '09' => '30',
      '10' => '31',
      '11' => '30',
      '12' => '31',
    ];
    $daysInTheMonth = (int) $yearArray[$parts[1]];
    $partialDateString = 'first monday ' . $parts[0] . '-' . $parts[1];
    $firstMonday = (int) date("j", strtotime($partialDateString));
    $calendarArray = [];
    $y = 1;
    for ($x = $firstMonday; $x <= $daysInTheMonth; $x++) {
      if (in_array($x, $singleNumArray)) {
        $x = '0' . $x;
      }
      $dateString = $parts[0] . '-' . $parts[1] . '-' . $x;
      $datedKeys = $this->getEventTextKeys($dateString, $eventsArray);
      $calendarArray[$x][$y][] = $this->createCalendarEventListings($datedKeys);
      ($y == 7) ? $y = 1 : $y++;
    }
    /* day name int (1 - 7) of last day in month */
    $lastDayOfMonth = (int) array_key_first($calendarArray[$daysInTheMonth]);
    /* fill in the blanks at the end of the month for our calendar grid */
    $b = 1;
    $firstDayNextMonth = $lastDayOfMonth + 1;
    for ($a = $firstDayNextMonth; $a <= 7; $a++) {
      if (in_array($b, $singleNumArray)) {
        $b = '0' . $b;
      }
      $newMonth = (int) $parts[1] + 1;
      if (in_array($newMonth, $singleNumArray)) {
        $newMonth = '0' . $newMonth;
      }
      $dateString = $parts[0] . '-' . $newMonth . '-' . $b;
      $datedKeys = $this->getEventTextKeys($dateString, $eventsArray);
      $calendarArray[$b][$a][] = $this->createCalendarEventListings($datedKeys);
      $b++;
    }
    $headDate = date('l F d, Y');
    $var = '<h2> Today is: ' . $headDate . '</h2>';
    $var .= '<div id="cal-head-row">';
    $var .= '<div class="day-name">Monday</div>';
    $var .= '<div class="day-name">Tuesday</div>';
    $var .= '<div class="day-name">Wednesday</div>';
    $var .= '<div class="day-name">Thursday</div>';
    $var .= '<div class="day-name">Friday</div>';
    $var .= '<div class="day-name">Saturday</div>';
    $var .= '<div class="day-name">Sunday</div>';
    $var .= '</div>';
    $var .= '<div id="cal-body-row">';
    foreach ($calendarArray as $date => $dayNum) {
      $dayNumber = array_key_first($dayNum);
      $dayName = $this->utilityService->getDayName($dayNumber);
      $var .= '<div class="cal-cell-' . $dayNumber . '">';
      $var .= '<h2>' . $date . '</h2>';
      $var .= '<div class="cal-data">' . $dayNum[$dayNumber][0] . '</div>';
      $var .= '</div>';
      ($dayName == 'Sunday') ? $var .= '<div class="break"></div>' : $var .= '';
    }
    $var .= '</div>';
    return $var;
  }

  /**
   * The updateCommunicoBlock method.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return array
   *   The form object.
   */
  public function updateCommunicoBlock(array $form, FormStateInterface $form_state) {
    $singleNumArray = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $block = $this->entityTypeManager->getStorage('block')->load('communicoplusfilterblock');
    if ($block) {
      $settings = $block->get('settings');
      $eventsLimit = $settings['communico_plus_filter_block_limit'];
    }
    else {
      $eventsLimit = '10';
    }
    /* date presets */
    $currentDate = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime($currentDate . "+1 days"));
    $sevenDaysHence = date('Y-m-d', strtotime($currentDate . "+7 days"));
    $fourteenDaysHence = date('Y-m-d', strtotime($currentDate . "+14 days"));
    $nextMonthStart = date('Y-m-d', strtotime('first day of +1 month'));
    $nextMonthEnd = date('Y-m-d', strtotime('last day of +1 month'));
    /* handle event types */
    $eventTypeRaw = $form_state->getValue('event_type');
    if ($eventTypeRaw != NULL && $eventTypeRaw != '') {
      $eventType = $this->utilityService->getEventTypeString($form_state->getValue('event_type'));
    }
    else {
      $eventType = FALSE;
    }
    /* handle ages */
    $ageRaw = $form_state->getValue('library_agegroup');
    if ($ageRaw != NULL && $ageRaw != '') {
      if ($ageRaw == 'all') {
        $eventAge = $this->utilityService->makeAllAgesString();
      }
      else {
        $eventAge = $ageRaw;
      }
    }
    else {
      $eventAge = FALSE;
    }
    /* handle event location */
    $eventLocationRaw = $form_state->getValue('library_location');
    if ($eventLocationRaw != NULL && $eventLocationRaw != '') {
      $location = $eventLocationRaw;
    }
    else {
      $location = $this->utilityService->makeAllLocationsString();
    }
    /* handle event dates */
    $eventDateRaw = $form_state->getValue('event_date');
    if ($eventDateRaw != NULL && $eventDateRaw != '') {
      switch ($eventDateRaw) {
        case $eventDateRaw == 'today':
          $blockStartDate = $currentDate;
          $blockEndDate = $currentDate;
          break;

        case $eventDateRaw == 'tomorrow':
          $blockStartDate = $tomorrow;
          $blockEndDate = $tomorrow;
          break;

        case $eventDateRaw == 'thisweek':
          $blockStartDate = $currentDate;
          $blockEndDate = $sevenDaysHence;
          break;

        case $eventDateRaw == 'nextweek':
          $blockStartDate = $sevenDaysHence;
          $blockEndDate = $fourteenDaysHence;
          break;

        case $eventDateRaw == 'nextmonth':
          $blockStartDate = $nextMonthStart;
          $blockEndDate = $nextMonthEnd;
          break;
      }
    }
    else {
      $blockStartDate = $currentDate;
      $blockEndDate = $sevenDaysHence;
    }
    /* layout */
    if ($this->requestStack->getCurrentRequest()->request->get('layout') == 1) {
      $eventDate = FALSE;
      $currentDate = date('Y-m-d');
      $parts = explode('-', $currentDate);
      $partialDateString = 'first monday ' . $parts[0] . '-' . $parts[1];
      $firstMonday = (int) date("j", strtotime($partialDateString));
      if ($eventDateRaw != 'nextmonth') {
        $blockStartDate = $parts[0] . '-' . $parts[1] . '-' . $firstMonday;
        $newMonth = (int) $parts[1] + 1;
        if (in_array($newMonth, $singleNumArray)) {
          $newMonth = '0' . $newMonth;
        }
        $blockEndDate = $parts[0] . '-' . $newMonth . '-07';
      }
      else {
        $nextMonth = (int) $parts[1] + 1;
        if (in_array($nextMonth, $singleNumArray)) {
          $nextMonth = '0' . $nextMonth;
        }
        $partialNewDateString = 'first monday ' . $parts[0] . '-' . $nextMonth;
        $newMonthMonday = (int) date("j", strtotime($partialNewDateString));
        if (in_array($newMonthMonday, $singleNumArray)) {
          $newMonthMonday = '0' . $newMonthMonday;
        }
        $blockStartDate = $parts[0] . '-' . $nextMonth . '-' . $newMonthMonday;
        $newMonth = (int) $nextMonth + 1;
        if (in_array($newMonth, $singleNumArray)) {
          $newMonth = '0' . $newMonth;
        }
        $blockEndDate = $parts[0] . '-' . $newMonth . '-07';
        $eventDate = $blockStartDate;
      }
      $events = $this->connector->getEventsFeed(
        $blockStartDate,
        $blockEndDate,
        $eventType,
        $eventAge,
        $location,
        '500',
      );
      $renderedEventsString = $this->createCalendar($events, $eventDate);
    }
    if ($this->requestStack->getCurrentRequest()->request->get('layout') == 0) {
      $events = $this->connector->getEventsFeed(
        $blockStartDate,
        $blockEndDate,
        $eventType,
        $eventAge,
        $location,
        $eventsLimit
      );
      $renderedEventsString = $this->createWall($events);
    }

    $form['feed_area']['#markup'] = $renderedEventsString;

    $form_state
      ->set('layout', $form_state->getValue('layout'))
      ->set('event_date', $form_state->getValue('event_date'))
      ->set('event_type', $form_state->getValue('event_type'))
      ->set('library_location', $form_state->getValue('library_location'))
      ->set('library_agegroup', $form_state->getValue('library_agegroup'))
      ->setRebuild();

    return $form['feed_area'];
  }

  /**
   * The popIfEmpty function.
   *
   * @return array
   *   An array of data objects
   */
  public function popIfEmpty() {
    $eventsLimit = '10';
    $current_date = date('Y-m-d');
    $blockStartDate = $current_date;
    $blockEndDate = date('Y-m-d', strtotime($current_date . "+7 days"));
    $eventType = 'Family Program';
    $eventAge = $this->utilityService->makeAllAgesString();
    $location = $this->utilityService->makeAllLocationsString();
    $events = $this->connector->getEventsFeed(
      $blockStartDate,
      $blockEndDate,
      $eventType,
      $eventAge,
      $location,
      $eventsLimit
    );
    return $this->createWall($events);
  }

}
