<?php

/**
 * @file
 * Contains \Drupal\communico_plus\Form\CommunicoPlusFilterForm.
 */

namespace Drupal\communico_plus\Form;

use Drupal;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

use Drupal\communico_plus\Controller\CommunicoPlusController;

class CommunicoPlusFilterForm extends FormBase {

  /**
   * @return string
   */
  public function getFormId() {
    return 'communico_plus_filter_form';
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['layout_box'] = [
      '#markup' => '<div id="cp-layout-box">',
    ];

    $form['layout'] = [
      '#type' => 'radios',
      '#options' => [0 => 'Feed', 1 => 'Calendar'],
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

    $form['row_box'] = [
      '#markup' => '<div id="cp-row-box">',
    ];

    $form['announce'] = [
      '#markup' => '<div class="form-markup">Filter By: </div>',
    ];

    $form['library_location'] = [
      '#type' => 'select',
      '#options' => $this->locationDropdown(),
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
      '#options' => $this->agesDropdown(),
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
      '#options' => $this->typesDropdown(),
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
      '#options' => $this->datesDropdown(),
      '#empty_option' => $this->t('Date'),
      '#default_value' => $form_state->getValue('event_date'),
      '#ajax' => [
        'event' => 'change',
        'callback' => '::updateCommunicoBlock',
        'wrapper' => 'feed_area_wrapper',
      ],
    ];

    $form['row_box_end'] = [
      '#markup' => '</div>',
    ];

    $markup = $form_state->getValue('feed_area');
    if($markup == NULL || $markup == '') {
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
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
#
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    Drupal::logger('communico_plus')->warning(' submit never happens ');
    $form_state
      ->set('layout', $form_state->getValue('layout'))
      ->set('event_date', $form_state->getValue('event_date'))
      ->set('event_type', $form_state->getValue('event_type'))
      ->set('library_location', $form_state->getValue('library_location'))
      ->set('library_agegroup', $form_state->getValue('library_agegroup'))
      ->setRebuild(TRUE);
  }

  /**
   * @param null $events
   * @return string
   *
   */
  public function createWall($events = NULL) {
    $controller = new CommunicoPlusController();
    $renderer = Drupal::service('renderer');
    $config = Drupal::config('communico_plus.settings');
    $link_url = Drupal::request()->getSchemeAndHttpHost();
    $return = '';
    foreach ($events as $event) {
      ($event['eventImage'] != NULL) ? $imageUrl = $event['eventImage'] : $imageUrl = FALSE;
      $imageRenderArray = $controller->createEventImage($imageUrl, $event['eventId']);
      $full_link = $link_url . '/event/' . $event['eventId'];
      $url = Url::fromUri($full_link);
      $link = Link::fromTextAndUrl(t($event['title']), $url)->toString();
      $startTime = $controller->findHoursFromDatestring($event['eventStart']);
      $endTime = $controller->findHoursFromDatestring($event['eventEnd']);
      $date = date('Y-m-d H:i:s');
      $today_dt = new DrupalDateTime($date);
      $expire_dt = new DrupalDateTime($event['eventEnd']);

      $branchLink = $config->get('linkurl') . '/event/' . $event['eventId'] . '#branch';
      $var = '';
      $var .= '<div id="event-block">';

      $var .= '<div class="block-section">';
      $var .= '<div class="section-time">';

      if ($expire_dt < $today_dt) {
        $var .= 'This event is finished. The event ended on ' . $controller->formatDatestamp($event['eventEnd']);
      } else {
        if($startTime == '12:00 AM' && $endTime == '11:59 PM') {
          $var .= 'All Day';
        } else {
          $var .= $startTime.' - '.$endTime;
        }
      }
      $var .= '</div>';
      $var .= '</div>'; /* END .block-section */

      $var .= '<div class="block-section-center">';
      if($imageRenderArray) {
      $var .= '<div class="section-image">';
        $var .= $renderer->render($imageRenderArray);
        $var .= '</div>';
      }
      $var .='<h2>';
      $var .= $link;
      $var .= '</h2>';

      $var .= '<div class="c-feature">';
      $var .= '<a href = "'.$branchLink.'" target="_new">'.$event['locationName'].'</a>';
      $var .= '</div>';

      $var .= '<div class="c-feature">';
      $var .= 'Age Group: '.implode(', ',$event['ages']);
      $var .= '</div>';

      $var .= '<div class="c-feature-paragraph">';
      $var .= '<p>';
      $var .= $event['subTitle'];
      $var .= $event['shortDescription'];
      $var .= '</p>';
      $var .= '</div>';

      $var .= '</div>'; /* END .block-section-center */

      $var .= '<div class="block-section">';
      if($event['roomId'] != NULL || $event['roomId'] != '') {
        $var .= '<div class="section-button-contain">';
        $var .= '<div id="event-sub-button">Register</div>';
        $var .= '</div>';
      }
      $var .= '</div>'; /* END .block-section */

      $var .= '</div>'; /* END #event-block */
      $return .= $var;
    }
    return $return;
  }

  /**
   * @param $date
   * @param $array
   * @return array
   *
   */
  public function getEventTextKeys($date, $array) {
    $keyArray = [];
    foreach ($array as $key => $val) {
      if($val['eventDate'] == $date) {
        $keyArray[$key] = $val;
      }
    }
    return $keyArray;
  }

  /**
   * @param $datedArray
   * @return string
   *
   */
  public function createCalendarEventListings($datedArray) {
    $returnText = '';
    $link_url = Drupal::request()->getSchemeAndHttpHost();
    foreach($datedArray as $value) {
      $full_link = $link_url . '/event/' . $value['eventId'];
      $url = Url::fromUri($full_link);
      $link = Link::fromTextAndUrl($value['title'], $url)->toString();
      if($value['allDay'] == '1') {
        $returnText .= '<div class="cal-text">All day.</div>';
      } else{
        $returnText .= '<div class="cal-text">'.$value['eventStart'].' - '.$value['eventEnd'].'</div>';
      }
      $returnText .= '<div class="cal-title">'.$link.'</div>';
      $returnText .= '<br>';
    }
    return $returnText;
  }

  /**
   * @param false $current_date
   * @return string
   */
  public function createCalendar($events = NULL, $current_date = FALSE) {
    $singleNumArray = ['1','2','3','4','5','6','7','8','9'];
    $controller = new CommunicoPlusController();
    $newArray = [];
    foreach($events as $event) {
      $newArray[] = [
        'eventDate' => $controller->findDateFromDatestring($event['eventStart']),
        'eventStart' => $controller->findHoursFromDatestring($event['eventStart']),
        'eventEnd' => $controller->findHoursFromDatestring($event['eventEnd']),
        'allDay' => ($controller->checkIfOneday($event['eventStart'], $event['eventEnd'])) ? '1': '0',
        'title' => $event['title'],
        'eventId' => $event['eventId'],
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
      '12' => '31'
    ];
    $daysInTheMonth = (int) $yearArray[$parts[1]];
    $partialDateString = 'first monday ' . $parts[0].'-'.$parts[1];
    $firstMonday = (int) date("j", strtotime($partialDateString));
    $calendarArray = [];
    $y = 1;
    for ($x = $firstMonday; $x <= $daysInTheMonth; $x++) {
      if(in_array($x, $singleNumArray)) {
        $x = '0'.$x;
      }
      $dateString = $parts[0].'-'.$parts[1].'-'.$x;
      $datedKeys = $this->getEventTextKeys($dateString, $newArray);
      $calendarArray[$x][$y][] = $this->createCalendarEventListings($datedKeys);
      ($y == 7) ? $y = 1 : $y++;
    }
    /* day name int (1 - 7) of last day in month */
    $lastDayOfMonth = (int) array_key_first($calendarArray[$daysInTheMonth]);
    /* fill in the blanks at the end of the month for our calendar grid */
    $b = 1;
    $firstDayNextMonth = $lastDayOfMonth + 1;
    for ($a = $firstDayNextMonth; $a <= 7; $a++) {
      if(in_array($b, $singleNumArray)) {
        $b = '0'.$b;
      }
      $newMonth = (int) $parts[1] + 1;
      if(in_array($newMonth, $singleNumArray)) {
        $newMonth = '0'.$newMonth;
      }
      $dateString = $parts[0].'-'.$newMonth.'-'.$b;
      $datedKeys = $this->getEventTextKeys($dateString, $newArray);
      $calendarArray[$b][$a][] = $this->createCalendarEventListings($datedKeys);
      $b++;
    }
    $var = '';
    $var .= '<div id="cal-head-row">';
    $var .= '<div class="day-name">Monday</div>';
    $var .= '<div class="day-name">Tuesday</div>';
    $var .= '<div class="day-name">Wednesday</div>';
    $var .= '<div class="day-name">Thursday</div>';
    $var .= '<div class="day-name">Friday</div>';
    $var .= '<div class="day-name">Saturday</div>';
    $var .= '<div class="day-name">Sunday</div>';
    $var .=  '</div>';
    $var .= '<div id="cal-body-row">';
    foreach($calendarArray as $date => $dayNum) {
      $dayNumber = array_key_first($dayNum);
      $dayName = $this->getDayName($dayNumber);
      $var .= '<div class="cal-cell-'.$dayNumber.'">';
      $var .= '<h4>'.$date.'</h4>';
      $var .= '<div class="cal-data">'.$dayNum[$dayNumber][0].'</div>';
      $var .= '</div>';
      ($dayName == 'Sunday') ? $var .='<div class="break"></div>' : $var .='';
    }
    $var .=  '</div>';
    return $var;
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   *
   */
  public function updateCommunicoBlock(array $form, FormStateInterface $form_state) {
    $singleNumArray = ['1','2','3','4','5','6','7','8','9'];
    $connector = Drupal::service('communico_plus.connector');
    $block = Drupal\block\Entity\Block::load('communicoplusfilterblock');
    if ($block) {
      $settings = $block->get('settings');
      $eventsLimit = $settings['communico_plus_filter_block_limit'];
    } else {
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
    if($eventTypeRaw != NULL && $eventTypeRaw != '') {
      $eventType = communicoPlusGetEventTypeString($form_state->getValue('event_type'));
    } else {
      $eventType = FALSE;
    }
    /* handle ages */
    $ageRaw = $form_state->getValue('library_agegroup');
    if($ageRaw != NULL && $ageRaw != '') {
      if($ageRaw == 'all') {
        $eventAge = $this->makeAllAgesString();
      } else {
        $eventAge = $ageRaw;
      }
    } else {
      $eventAge = FALSE;
    }
    /* handle event location */
    $eventLocationRaw = $form_state->getValue('library_location');
    if($eventLocationRaw != NULL && $eventLocationRaw != '') {
      $location = $eventLocationRaw;
    } else {
      $location = $this->makeAllLocationsString();
    }
    /* handle event dates */
    $eventDateRaw = $form_state->getValue('event_date');
    if($eventDateRaw != NULL && $eventDateRaw != '') {
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
    } else {
      $blockStartDate = $currentDate;
      $blockEndDate = $sevenDaysHence;
    }
    /* layout */
    if(Drupal::request()->request->get('layout') == 1) {
      $eventDate = FALSE;
      $currentDate = date('Y-m-d');
      $parts = explode('-', $currentDate);
      $partialDateString = 'first monday ' . $parts[0].'-'.$parts[1];
      $firstMonday = (int) date("j", strtotime($partialDateString));
      if($eventDateRaw != 'nextmonth') {
        $blockStartDate = $parts[0].'-'.$parts[1].'-'.$firstMonday;
        $newMonth = (int) $parts[1] + 1;
        if(in_array($newMonth, $singleNumArray)) {
          $newMonth = '0'.$newMonth;
        }
        $blockEndDate = $parts[0].'-'.$newMonth.'-'.'07';
      }
      else {
        $nextMonth = (int) $parts[1] + 1;
        if(in_array($nextMonth, $singleNumArray)) {
          $nextMonth = '0'.$nextMonth;
        }
        $partialNewDateString = 'first monday '.$parts[0].'-'.$nextMonth;
        $newMonthMonday = (int) date("j", strtotime($partialNewDateString));
        if(in_array($newMonthMonday, $singleNumArray)) {
          $newMonthMonday = '0'.$newMonthMonday;
        }
        $blockStartDate = $parts[0].'-'.$nextMonth.'-'.$newMonthMonday;
        $newMonth = (int) $nextMonth + 1;
        if(in_array($newMonth, $singleNumArray)) {
          $newMonth = '0'.$newMonth;
        }
        $blockEndDate = $parts[0].'-'.$newMonth.'-'.'07';
        $eventDate = $blockStartDate;
      }
      $events = $connector->getEventsFeed(
        $blockStartDate,
        $blockEndDate,
        $eventType,
        $eventAge,
        $location,
        '500',
      );
      $renderedEventsString = $this->createCalendar($events, $eventDate);
    }
    else {
      $events = $connector->getEventsFeed(
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
   * @param array $form
   * @param FormStateInterface $form_state
   * @return mixed|string
   */
  function popIfEmpty() {
    $connector = Drupal::service('communico_plus.connector');
    $eventsLimit = '10';
    $current_date = date('Y-m-d');
    $blockStartDate = $current_date;
    $blockEndDate = date('Y-m-d', strtotime($current_date . "+7 days"));
    $eventType = 'Family Program';
    $eventAge = $this->makeAllAgesString();
    $location = $this->makeAllLocationsString();
    $events = $connector->getEventsFeed(
      $blockStartDate,
      $blockEndDate,
      $eventType,
      $eventAge,
      $location,
      $eventsLimit
    );
    return $this->createWall($events);
  }

  /**
   * @return string[]
   * makes an age group dropdown array
   * @TODO fix this with var input from api
   */
  public function agesDropdown() {
    $dropdownArray = [
      'all' => 'All ages',
      'Birth - 5' => 'Birth - 5',
      '5 - 12 Years Old' => '5 - 12 Years Old',
      '13 - 19 Years Old (Teens)' => '13 - 19 Years Old (Teens)',
      'Adults' => 'Adults',
      'Seniors' => 'Seniors',
    ];
    return $dropdownArray;
  }

  /**
   * @param $daynumber
   * @return string
   *
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
   * @return string
   *
   */
  public function makeAllAgesString() {
    $newAgeString = '';
      $dropdownArray = $this->agesDropdown();
      foreach($dropdownArray as $age) {
        if($age != 'All ages') {
          $newAgeString .= $age . ',';
        }
      }
    return substr($newAgeString, 0, -1);
  }

  /**
   * @return string
   *
   */
  public function makeAllLocationsString() {
    $dropdownArray = $this->locationDropdown();
    $newLocationString = '';
    foreach($dropdownArray as $key => $value) {
      $newLocationString .= $key.',';
    }
    return substr($newLocationString, 0, -1);
  }

  /**
   * @return array
   * creates a library locations dropdown array
   *
   */
  public function locationDropdown() {
    $dropdownArray = [];
    $db = Drupal::service('database');
    $return = $db->select('communico_locations', 'n')
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
    $db = Drupal::service('database');
    $return = $db->select('communico_types', 'n')
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
   * @return string[]
   *
   */
  public function datesDropdown() {
    $timeSelects = [
      'today' =>'Today',
      'tomorrow' => 'Tomorrow',
      'thisweek' => 'This Week',
      'nextweek' => 'Next Week',
      'nextmonth' => 'Next Month',
    ];
    return $timeSelects;
  }






}
