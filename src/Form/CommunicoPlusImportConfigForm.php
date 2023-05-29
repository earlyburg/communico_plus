<?php

/**
 * @file
 * Contains \Drupal\communico_plus\Form\CommunicoPlusImportConfigForm.
 */

namespace Drupal\communico_plus\Form;

use Drupal\communico_plus\Service\ConnectorService;
use Drupal\Core\Logger\LoggerChannelFactory;
use Exception;
use Psr\Container\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\communico_plus\Service\UtilityService;
class CommunicoPlusImportConfigForm extends ConfigFormBase {

  /**
   * @var UtilityService $utilityService
   */
  protected UtilityService $utilityService;

  /**
   * @var ConnectorService $connector
   */
  protected ConnectorService $connector;

  /**
   * Messenger service.
   *
   * @var LoggerChannelFactory $logger_factory
   */
  protected $loggerFactory;

  /**
   * Drupal config factory interface.
   *
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Config settings.
   *
   * @var string
   */
  const COMMUNICO_PLUS_IMPORT_SETTINGS = 'communico_plus.import.settings';

  /**
   * @param UtilityService $utility_service
   * @param ConnectorService $communico_plus_connector
   * @param ConfigFactoryInterface $config_factory
   */
  public function __construct(
    UtilityService $utility_service,
    ConnectorService $communico_plus_connector,
    LoggerChannelFactory $logger_factory,
    ConfigFactoryInterface $config_factory) {
    $this->utilityService = $utility_service;
    $this->connector = $communico_plus_connector;
    $this->loggerFactory = $logger_factory;
    parent::__construct($config_factory);
  }

  /**
   * @param ContainerInterface $container
   * @return CommunicoPlusImportConfigForm|ConfigFormBase|static
   * @throws \Psr\Container\ContainerExceptionInterface
   * @throws \Psr\Container\NotFoundExceptionInterface
   *
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('communico_plus.utilities'),
      $container->get('communico_plus.connector'),
      $container->get('logger.factory'),
      $container->get('config.factory'),
    );
  }

  /**
   * @return string
   */
  public function getFormId() {
    return 'communico_plus_import_config_form';
  }

  /**
   * @return string[]
   *
   */
  protected function getEditableConfigNames() {
    return [
      static::COMMUNICO_PLUS_IMPORT_SETTINGS,
    ];
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['admin_library_location'] = [
      '#type' => 'select',
      '#title' => 'Library Import Location',
      '#options' => $this->utilityService->locationDropdown(),
      '#empty_option' => $this->t('Library'),
      '#description' => $this->t('Select the library location to import events from, and save configuration.'),
    ];

    $libraryText = '<div><i>Imports events from today\'s date to the last day of the following month.</i></div>';
    $libraryText .= '<h3>The following library locations have events stored in Drupal:</h3>';
    $currentLibraries = $this->utilityService->getStoredLibraryLocations();
    foreach($currentLibraries as $library) {
      $libraryText .= '<div>'.$library.'</div>';
    }

    $form['admin_library_locations_status'] = [
      '#markup' => $libraryText,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @throws Exception
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $formValues = $form_state->getValues();
    if (array_key_exists('admin_library_location', $formValues) && !empty($formValues['admin_library_location'])) {
      $location = $formValues['admin_library_location'];
      $type = null;
      $age = null;
      $start_date = date('Y-m-d');
      $end_date = date('Y-m-d', strtotime('last day of +1 month'));
      $limit = 500;
      $events = $this->connector->getEventsFeed($start_date, $end_date, $type, $age, $location, $limit);
      foreach ($events as $event) {
        if(!$this->utilityService->checkEventExists($event['eventId'])) {
          try {
            $this->utilityService->createEventPageNode($event);
          } catch (Exception $e) {
            $this->loggerFactory->get('communico_plus')
              ->error('Function createEventPageNode() returned - ' . $e);
          }
        }
      }
    }
    parent::submitForm($form, $form_state);
  }


}
