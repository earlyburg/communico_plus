<?php

namespace Drupal\communico_plus\Form;

use Drupal\communico_plus\Service\ConnectorService;
use Drupal\Core\Logger\LoggerChannelFactory;
use Psr\Container\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\communico_plus\Service\UtilityService;

/**
 * The CommunicoPlusImportConfigForm class.
 */
class CommunicoPlusImportConfigForm extends ConfigFormBase {

  /**
   * The utility service.
   *
   * @var \Drupal\communico_plus\Service\UtilityService
   */
  protected UtilityService $utilityService;

  /**
   * The connector service.
   *
   * @var \Drupal\communico_plus\Service\ConnectorService
   */
  protected ConnectorService $connector;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Config settings.
   *
   * @var string
   */
  const COMMUNICO_PLUS_IMPORT_SETTINGS = 'communico_plus.import.settings';

  /**
   * CommunicoPlusImportConfigForm constructor.
   *
   * @param \Drupal\communico_plus\Service\UtilityService $utility_service
   *   The utility service.
   * @param \Drupal\communico_plus\Service\ConnectorService $communico_plus_connector
   *   The Communico Plus connector service.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(
    UtilityService $utility_service,
    ConnectorService $communico_plus_connector,
    LoggerChannelFactory $logger_factory,
    EntityTypeManagerInterface $entity_manager,
  ) {
    $this->utilityService = $utility_service;
    $this->connector = $communico_plus_connector;
    $this->loggerFactory = $logger_factory;
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * The create method.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   The container interface.
   *
   * @return CommunicoPlusImportConfigForm|ConfigFormBase|static
   *   Returns an instance of this form class.
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
   * The getFormId function.
   *
   * @return string
   *   The form ID.
   */
  public function getFormId() {
    return 'communico_plus_import_config_form';
  }

  /**
   * The getEditableConfigNames method.
   *
   * @return static
   *   The editable config names.
   */
  protected function getEditableConfigNames() {
    return [
      static::COMMUNICO_PLUS_IMPORT_SETTINGS,
    ];
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
    foreach ($currentLibraries as $library) {
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
    foreach ($currentLibraries as $library) {
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
   * The validateForm method.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * The submitForm method.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $formValues = $form_state->getValues();
    if (array_key_exists('admin_library_location', $formValues) && !empty($formValues['admin_library_location'])) {
      $location = $formValues['admin_library_location'];
      $type = NULL;
      $age = NULL;
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
        'finished' => 'communico_plus_finished',
      ];
      foreach ($events as $event) {
        if (!$this->utilityService->checkEventExists($event['eventId'])) {
          $batch['operations'][] = ['communico_plus_create_event_page_node', [$event]];
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
