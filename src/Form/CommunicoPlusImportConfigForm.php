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
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Config settings.
   *
   * @var string
   */
  const COMMUNICO_PLUS_IMPORT_SETTINGS = 'communico_plus.import.settings';

  /**
   * @param UtilityService $utility_service
   * @param ConnectorService $communico_plus_connector
   * @param LoggerChannelFactory $logger_factory
   * @param EntityTypeManagerInterface $entity_manager
   */
  public function __construct(
    UtilityService $utility_service,
    ConnectorService $communico_plus_connector,
    LoggerChannelFactory $logger_factory,
    EntityTypeManagerInterface $entity_manager) {
    $this->utilityService = $utility_service;
    $this->connector = $communico_plus_connector;
    $this->loggerFactory = $logger_factory;
    $this->entityTypeManager = $entity_manager;
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
      $container->get('entity_type.manager'),
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
    $config = $this->config(static::COMMUNICO_PLUS_IMPORT_SETTINGS);
    $form['imports'] = [
      '#type' => 'details',
      '#title' => $this
        ->t('Import Settings'),
      '#open' => TRUE,
    ];



    $form['imports']['admin_library_location'] = [
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
      $libraryText .= '<div>' . $library . '</div>';
    }

    $form['imports']['admin_library_locations_status'] = [
      '#markup' => $libraryText,
    ];

    $form['manage'] = [
      '#type' => 'details',
      '#title' => $this
        ->t('Import Management Settings'),
      '#open' => TRUE,
    ];

    $updateText = '<div><i><b>The following locations can have new events automatically imported.</b></i></div>';
    foreach($currentLibraries as $library) {
      $updateText .= '<div>' . $library . '</div>';
    }
    $form['manage']['update_information'] = [
      '#markup' => $updateText,
    ];

    $form['manage']['auto_update_events'] = [
      '#type' => 'checkbox',
      '#title' => 'Download and save events automatically when Drupal Cron runs.',
      '#default_value' => $config->get('auto_update_events'),
    ];

    $form['manage']['delete_unpublished'] = [
      '#type' => 'checkbox',
      '#title' => 'Delete all unpublished Event nodes automatically when Drupal Cron runs.',
      '#default_value' => $config->get('delete_unpublished'),
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
      $batch = [
        'title' => $this->t('Importing Events...'),
        'operations' => [],
        'init_message' => $this->t('Initializing...'),
        'progress_message' => $this->t('Processed @current out of @total.'),
        'error_message' => $this->t('An error occurred during processing'),
        'finished' => 'communicoPlusFinished',
      ];
      foreach ($events as $event) {
        if (!$this->utilityService->checkEventExists($event['eventId'])) {
          $batch['operations'][] = ['createEventPageNode', [$event]];
        }
      }
      batch_set($batch);
    }

    $this->config(static::COMMUNICO_PLUS_IMPORT_SETTINGS)
      ->set('auto_update_events', $form_state->getValue('auto_update_events'))
      ->set('delete_unpublished', $form_state->getValue('delete_unpublished'))
      ->save();
    parent::submitForm($form, $form_state);
  }


}
