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
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
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
   * @var ConnectorService
   */
  protected ConnectorService $connectorService;

  /**
   * Drupal config factory interface.
   *
   * @var ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Symphony http request stack
   *
   * @var RequestStack $requestStack
   */
  private RequestStack $requestStack;

  /**
   * The date formatter service.
   *
   * @var DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * @var UtilityService $utilityService
   */
  protected UtilityService $utilityService;

  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param ConnectorService $connector_service
   * @param ConfigFactoryInterface $config_factory
   * @param RequestStack $requestStack
   * @param DateFormatterInterface $date_formatter
   * @param UtilityService $utility_service
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConnectorService $connector_service,
    ConfigFactoryInterface $config_factory,
    RequestStack $requestStack,
    DateFormatterInterface $date_formatter,
    UtilityService $utility_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connectorService = $connector_service;
    $this->configFactory = $config_factory;
    $this->requestStack = $requestStack;
    $this->dateFormatter = $date_formatter;
    $this->utilityService = $utility_service;
  }

  /**
   * @param ContainerInterface $container
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @return CommunicoPlusBlock|static
   * @throws ContainerExceptionInterface
   * @throws NotFoundExceptionInterface
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
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $build = array(
      '#theme' => 'communico_plus_block',
      '#events' => $this->buildCommunicoPlusBlock($config),
    );
    $build['#cache']['max-age'] = 0;
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['communico_plus_block_type'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Event Types'),
      '#description' => $this->t('Make sure these are a valid event type in Communico. Separate multiple values with a comma'),
      '#default_value' => isset($config['communico_plus_block_type']) ? $config['communico_plus_block_type'] : '',
    );

    $form['communico_plus_block_start'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Start Date'),
      '#description' => $this->t('Date you would like to display events starting with in YYYY-MM-DD format, leave blank to always start at the latest days events.'),
      '#default_value' => isset($config['communico_plus_block_start']) ? $config['communico_plus_block_start'] : '',
    );

    $form['communico_plus_block_end'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('End Date'),
      '#description' => $this->t('Date you would like to display events ending with in YYYY-MM-DD format, leave blank to always view 5 days of events.'),
      '#default_value' => isset($config['communico_plus_block_end']) ? $config['communico_plus_block_end'] : '',
    );

    $form['communico_plus_block_limit'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('Limit the number of results returned'),
      '#default_value' => isset($config['communico_plus_block_limit']) ? $config['communico_plus_block_limit'] : '10',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['communico_plus_block_type'] = $form_state->getValue('communico_plus_block_type');
    $this->configuration['communico_plus_block_start'] = $form_state->getValue('communico_plus_block_start');
    $this->configuration['communico_plus_block_end'] = $form_state->getValue('communico_plus_block_end');
    $this->configuration['communico_plus_block_limit'] = $form_state->getValue('communico_plus_block_limit');
  }

  /**
   * @param $config
   * @return array
   *
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
    $rendered_events = array();
    $link_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

    foreach ($events as $event) {
      $branchLinkString = $this->configFactory->get('communico_plus.settings')->get('linkurl').'/event/'.$event['eventId'].'#branch';
      $branchLink = '<a href="'.$branchLinkString.'" target="_new">'.$event['locationName'].'</a>';
      $full_link = $link_url . '/event/' . $event['eventId'];
      $url = Url::fromUri($full_link);
      $link = Link::fromTextAndUrl($this->t($event['title']), $url )->toString();
      $period = $this->utilityService->checkIfOneday($event['eventStart'], $event['eventEnd']);
      if($period) {
        $eventEnd = ' '.$period;
      }
      else {
        $eventEnd = $this->utilityService->formatDateStamp($event['eventEnd']);
      }
      $rendered_events[] = array(
        '#theme' => 'communico_plus_item',
        '#title_link' => $link,
        '#start_date' => $this->utilityService->formatDateStamp($event['eventStart']),
        '#end_date' => $eventEnd,
        '#location' => [
          '#markup' => $branchLink
        ],
        '#room' => $event['roomName']
      );
    }
    return $rendered_events;
  }

}
