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
  protected ConnectorService $connectorService;

  /**
   * Drupal config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The Symfony http request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private RequestStack $requestStack;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The communico plus utility service.
   *
   * @var \Drupal\communico_plus\Service\UtilityService
   */
  protected UtilityService $utilityService;

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
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connectorService = $connector_service;
    $this->configFactory = $config_factory;
    $this->requestStack = $requestStack;
    $this->dateFormatter = $date_formatter;
    $this->utilityService = $utility_service;
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
    $config = $this->getConfiguration();
    $form['communico_plus_block_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Types'),
      '#description' => $this->t('Make sure these are a valid event type in Communico. Separate multiple values with a comma'),
      '#default_value' => $config['communico_plus_block_type'] ?? '',
    ];

    $form['communico_plus_block_start'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start Date'),
      '#description' => $this->t('Date you would like to display events starting with in YYYY-MM-DD format, leave blank to always start at the latest days events.'),
      '#default_value' => $config['communico_plus_block_start'] ?? '',
    ];

    $form['communico_plus_block_end'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End Date'),
      '#description' => $this->t('Date you would like to display events ending with in YYYY-MM-DD format, leave blank to always view 5 days of events.'),
      '#default_value' => $config['communico_plus_block_end'] ?? '',
    ];

    $form['communico_plus_block_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('Limit the number of results returned'),
      '#default_value' => $config['communico_plus_block_limit'] ?? '10',
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
    $this->configuration['communico_plus_block_type'] = $form_state->getValue('communico_plus_block_type');
    $this->configuration['communico_plus_block_start'] = $form_state->getValue('communico_plus_block_start');
    $this->configuration['communico_plus_block_end'] = $form_state->getValue('communico_plus_block_end');
    $this->configuration['communico_plus_block_limit'] = $form_state->getValue('communico_plus_block_limit');
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
    if ($config['communico_plus_block_start'] == NULL || $config['communico_plus_block_start'] == '') {
      $config['communico_plus_block_start'] = date('Y-m-d');
    }
    if ($config['communico_plus_block_end'] == NULL || $config['communico_plus_block_end'] == '') {
      $current_date = date('Y-m-d');
      $config['communico_plus_block_end'] = date('Y-m-d', strtotime($current_date . "+7 days"));
    }
    $events = $this->connectorService->getFeed(
      $config['communico_plus_block_start'],
      $config['communico_plus_block_end'],
      $config['communico_plus_block_type'],
      $config['communico_plus_block_limit']);
    $rendered_events = [];
    $link_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

    foreach ($events as $event) {
      $branchLinkString = $this->configFactory->get('communico_plus.settings')->get('linkurl') . '/event/' . $event['eventId'] . '#branch';
      $branchLink = '<a href="' . $branchLinkString . '" target="_new">' . $event['locationName'] . '</a>';
      $full_link = $link_url . '/event/' . $event['eventId'];
      $url = Url::fromUri($full_link);
      $link = Link::fromTextAndUrl($event['title'], $url)->toString();
      $period = $this->utilityService->checkIfOneday($event['eventStart'], $event['eventEnd']);
      if ($period) {
        $eventEnd = ' ' . $period;
      }
      else {
        $eventEnd = $this->utilityService->formatDateStamp($event['eventEnd']);
      }
      $rendered_events[] = [
        '#theme' => 'communico_plus_item',
        '#title_link' => $link,
        '#start_date' => $this->utilityService->formatDateStamp($event['eventStart']),
        '#end_date' => $eventEnd,
        '#location' => [
          '#markup' => $branchLink,
        ],
        '#room' => $event['roomName'],
      ];
    }
    return $rendered_events;
  }

}
