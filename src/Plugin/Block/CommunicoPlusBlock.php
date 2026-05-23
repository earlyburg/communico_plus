<?php

namespace Drupal\communico_plus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\communico_plus\Service\ConnectorService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\communico_plus\Service\UtilityService;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a basic Communico events Block.
 *
 * @Block(
 *   id = "communico_plus_block",
 *   admin_label = @Translation("Communico Plus Block"),
 * )
 */
class CommunicoPlusBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Communico connector service.
   *
   * @var \Drupal\communico_plus\Service\ConnectorService
   */
  protected $connectorService;

  /**
   * Drupal config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Symfony http request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The communico plus utility service.
   *
   * @var \Drupal\communico_plus\Service\UtilityService
   */
  protected $utilityService;

  /**
   * The Drupal page manager interface.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a Communico Plus Block.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin id string.
   * @param mixed $plugin_definition
   *   The plugin definition array.
   * @param \Drupal\communico_plus\Service\ConnectorService $connector_service
   *   The Communico Plus connector service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Drupal config factory interface.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Symfony http request stack.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The Drupal date formatter service.
   * @param \Drupal\communico_plus\Service\UtilityService $utility_service
   *   The Communico Plus utility service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The Drupal page manager interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Drupal entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConnectorService $connector_service,
    ConfigFactoryInterface $config_factory,
    RequestStack $requestStack,
    DateFormatterInterface $date_formatter,
    UtilityService $utility_service,
    PagerManagerInterface $pager_manager,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connectorService = $connector_service;
    $this->configFactory = $config_factory;
    $this->requestStack = $requestStack;
    $this->dateFormatter = $date_formatter;
    $this->utilityService = $utility_service;
    $this->pagerManager = $pager_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Psr\Container\ContainerInterface $container
   *   The container interface.
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin id string.
   * @param mixed $plugin_definition
   *   The plugin definition array.
   *
   * @return static
   *   The instantiated block object.
   *
   * @throws \Psr\Container\ContainerExceptionInterface
   * @throws \Psr\Container\NotFoundExceptionInterface
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('communico_plus.connector'),
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('date.formatter'),
      $container->get('communico_plus.utilities'),
      $container->get('pager.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * The build method.
   */
  public function build() {
    $config = $this->getConfiguration();
    $build = [
      '#theme' => 'communico_plus_block',
      '#events' => $this->buildCommunicoPlusBlock($config),
    ];
    $build['#cache']['max-age'] = 0;
    return $build;
  }

  /**
   * The blockForm method.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['communico_plus_block_type'] = [
      '#type' => 'select',
      '#options' => $this->utilityService->typesDropdown(),
      '#title' => $this->t('Event Types'),
      '#description' => $this->t('Make sure these are a valid event type.'),
      '#default_value' => $this->configuration['communico_plus_block_type'] ?? '',
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['communico_plus_block_start'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      '#description' => $this->t('Date you would like to display events starting with in YYYY-MM-DD format, leave blank to always start at the latest days events.'),
      '#default_value' => $this->configuration['communico_plus_block_start'] ?? '',
    ];

    $form['communico_plus_block_end'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      '#description' => $this->t('Date you would like to display events ending with in YYYY-MM-DD format, leave blank to always view 5 days of events.'),
      '#default_value' => $this->configuration['communico_plus_block_end'] ?? '',
    ];

    $form['communico_plus_pager_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('Limit the number of results returned'),
      '#default_value' => $this->configuration['communico_plus_pager_limit'] ?? '10',
    ];

    return $form;
  }

  /**
   * The blockSubmit function.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['communico_plus_block_type'] = $values['communico_plus_block_type'];
    $this->configuration['communico_plus_block_start'] = $values['communico_plus_block_start'];
    $this->configuration['communico_plus_block_end'] = $values['communico_plus_block_end'];
    $this->configuration['communico_plus_pager_limit'] = $values['communico_plus_pager_limit'];
  }

  /**
   * The buildCommunicoPlusBlock method.
   *
   * @param array $config
   *   The block configuration array.
   *
   * @return array
   *   The render array for the block content.
   */
  public function buildCommunicoPlusBlock($config) {
    $renderedEvents = [];
    $eventStorage = $this->entityTypeManager->getStorage('node');
    $eventQuery = $eventStorage->getQuery()
      ->condition('type', 'event_page')
      ->condition('field_communico_event_type', $this->utilityService->getEventTypeString($config['communico_plus_block_type']))
      ->condition('field_communico_start_date', $config['communico_plus_block_start'], '>=')
      ->condition('field_communico_end_date', $config['communico_plus_block_end'], '<=')
      ->sort('field_communico_start_date', 'ASC')
      ->accessCheck(FALSE);

    $nids = $eventQuery->execute();
    $numberOfEvents = count($nids);
    $pager = $this->pagerManager->createPager($numberOfEvents, $config['communico_plus_pager_limit']);
    $currentPage = $pager->getCurrentPage();
    $offset = $currentPage * $config['communico_plus_pager_limit'];

    $eventNids = $eventQuery->range($offset, $config['communico_plus_pager_limit'])->execute();

    $loadedEvents = $eventStorage->loadMultiple($eventNids);

    $link_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

    foreach ($loadedEvents as $event) {
      $branchLinkString = $this->configFactory->get('communico_plus.settings')->get('linkurl') . '/event/' . $event->get('field_communico_event_id')->value . '#branch';
      $branchLink = '<a href="' . $branchLinkString . '" target="_new">' . $event->get('field_communico_library_location')->value . '</a>';
      $full_link = $link_url . '/event/' . $event->get('field_communico_event_id')->value;
      $url = Url::fromUri($full_link);
      $link = Link::fromTextAndUrl($event->get('title')->value, $url)->toString();
      $period = $this->utilityService->checkIfOneday($event->get('field_communico_start_date')->value, $event->get('field_communico_end_date')->value);

      if ($period) {
        $eventEnd = ' ' . $period;
      }
      else {
        $eventEnd = $this->utilityService->formatDateStamp($event->get('field_communico_end_date')->value);
      }
      $renderedEvents[] = [
        'content' => [
          '#theme' => 'communico_plus_item',
          '#title_link' => $link,
          '#start_date' => $this->utilityService->formatDateStamp($event->get('field_communico_start_date')->value),
          '#end_date' => $eventEnd,
          '#location' => [
            '#markup' => $branchLink,
          ],
        ],
      ];
    }
    $renderedEvents[] = [
      'pager' => [
        '#type' => 'pager',
      ],
    ];
    return $renderedEvents;
  }

}
