<?php
/**
 * @file
 * Contains \Drupal\communico_plus\Service\UtilityService.
 */
namespace Drupal\communico_plus\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\StreamWrapper\PublicStream;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Psr\Container\NotFoundExceptionInterface;

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
   * @param ConfigFactoryInterface $config
   * @param LoggerChannelFactory $logger_factory
   * @param DateFormatterInterface $date_formatter
   * @param FileSystemInterface $file_system
   * @param ImageFactory $image_factory
   */
  public function __construct(
    ConfigFactoryInterface $config,
    LoggerChannelFactory $logger_factory,
    DateFormatterInterface $date_formatter,
    FileSystemInterface $file_system,
    ImageFactory $image_factory) {
    $this->config = $config;
    $this->loggerFactory = $logger_factory;
    $this->dateFormatter = $date_formatter;
    $this->fileSystem = $file_system;
    $this->imageFactory = $image_factory;
  }

  /**
   * {@inheritdoc}
   *
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

}
