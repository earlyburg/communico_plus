<?php

namespace Drupal\communico_plus\Plugin\Block;

use Drupal;
use Drupal\Core\Block\BlockBase;
use Drupal\communico_plus\Controller\CommunicoPlusController;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a basic Communico events Block.
 *
 * @Block(
 *   id = "communico_plus_block",
 *   admin_label = @Translation("Communico Plus Block"),
 * )
 */
class CommunicoPlusBlock extends BlockBase {

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
    $connector = Drupal::service('communico_plus.connector');
    $controller = new CommunicoPlusController();
    $communico_config = Drupal::config('communico_plus.settings');
    if ($config['communico_plus_block_start'] == NULL || $config['communico_plus_block_start'] == '') {
      $config['communico_plus_block_start'] = date('Y-m-d');
    }
    if ($config['communico_plus_block_end'] == NULL || $config['communico_plus_block_end'] == '') {
      $current_date = date('Y-m-d');
      $config['communico_plus_block_end'] = date('Y-m-d', strtotime($current_date . "+7 days"));
    }
    $events = $connector->getFeed(
      $config['communico_plus_block_start'],
      $config['communico_plus_block_end'],
      $config['communico_plus_block_type'],
      $config['communico_plus_block_limit']);
    $rendered_events = array();
    $link_url = Drupal::request()->getSchemeAndHttpHost();

    foreach ($events as $event) {
      $branchLinkString = $communico_config->get('linkurl').'/event/'.$event['eventId'].'#branch';
      $branchLink = '<a href = "'.$branchLinkString.'" target="_new">'.$event['locationName'].'</a>';
      $full_link = $link_url . '/event/' . $event['eventId'];
      $url = Url::fromUri($full_link);
      $link = Link::fromTextAndUrl(t($event['title']), $url )->toString();
      $period = $controller->checkIfOneday($event['eventStart'], $event['eventEnd']);
      if($period != FALSE) {
        $eventEnd = ' '.$period;
      }
      else {
        $eventEnd = $controller->formatDateStamp($event['eventEnd']);
      }
      $rendered_events[] = array(
        '#theme' => 'communico_plus_item',
        '#title_link' => $link,
        '#start_date' => $controller->formatDateStamp($event['eventStart']),
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
