<?php

namespace Drupal\communico_plus\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PublicStream;

class CommunicoPlusController extends ControllerBase {

  private $connector;

  private $config;

  /**
   * CommunicoPlus Controller constructor.
   *
   */
  public function __construct() {
    $this->connector = Drupal::service('communico_plus.connector');
    $this->config = Drupal::config('communico_plus.settings');
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
    $branchLink = $this->config->get('linkurl').'/event/'.$event['data']['eventId'].'#branch';
    $calendarImagePath = '/'.Drupal::service('module_handler')
        ->getModule('communico_plus')
        ->getPath() . '/images/calendar.png';
    $map_pinImagePath = '/'.Drupal::service('module_handler')
        ->getModule('communico_plus')
        ->getPath() . '/images/map_pin.png';
    $var = '';
    $var .='<h1 class="page-title">';
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
      Drupal::messenger()->addWarning('This event is finished. The event ended on ' . $this->formatDatestamp($event['data']['eventEnd']));
      $var .= 'This event is finished. The event ended on ' . $this->formatDatestamp($event['data']['eventEnd']);
    } else {
      $var .= 'This event starts on '.$this->formatDatestamp($event['data']['eventStart']);
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
      'one_image' => $this->createEventImage($imageUrl, $eventId),
    ];
    return $return;
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
    $path = Drupal::service('file_system')->realpath('.') . '/' . PublicStream::basePath().'/event_images';
    if (!Drupal::service('file_system')->prepareDirectory($path)) {
      Drupal::service('file_system')->mkdir($path);
    }
    $ext = pathinfo($imageUrl, PATHINFO_EXTENSION);
    if($ext != NULL && $ext != '') {
      $file_path_physical = $path . '/' . $eventId . '.' . $ext;
      /* check if the image already exists */
      if(file_exists($file_path_physical)) {
        $image = Drupal::service('image.factory')->get($file_path_physical);
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
        $savedFile = Drupal::service('file_system')->saveData($fileOb, $file_path_physical, FileSystemInterface::EXISTS_REPLACE);
        $image = Drupal::service('image.factory')->get($savedFile);
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
   * @param null $registrationId
   * @return string[]
   *
   */
  public function reservation($registrationId = NULL) {
    $registration = $this->connector->getRegistration($registrationId);
    $date = date('Y-m-d H:i:s');
    $today_dt = new DrupalDateTime($date);
    $expire_dt = new DrupalDateTime($registration['data']['eventEnd']);
    $branchLink = $this->config->get('linkurl').'/event/'.$registration['data']['eventId'].'#branch';
    $var = '';
    $var .='<h1 class="page-title">';
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
      Drupal::messenger()->addWarning('This event is finished. The event ended on ' . $this->formatDatestamp($registration['data']['eventEnd']));
      $var .= 'This event is finished. The event ended on ' . $this->formatDatestamp($registration['data']['eventEnd']);
    } else {
      $var .= 'This event starts on '.$this->formatDatestamp($registration['data']['eventStart']);
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

  /**
   * @param null $dateString
   * @return false|string
   * formats a Communico date into a more readable format
   */
  public function formatDatestamp($dateString = NULL) {
    $date_formatter = Drupal::service('date.formatter');
    $type = 'medium';
    $dateObject = new DrupalDateTime($dateString);
    $timestamp = $dateObject->getTimestamp();
    $formatted = $date_formatter->format($timestamp, $type, '');
    $cleanDate = substr($formatted, 0, strpos($formatted, " -"));
    return $cleanDate;
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

}
