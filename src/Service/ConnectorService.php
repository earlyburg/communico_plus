<?php
/**
 * @file
 * Contains \Drupal\communico_plus\Service\ConnectorService.
 */
namespace Drupal\communico_plus\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Drupal\Core\State\State;
use Drupal\Core\Logger\LoggerChannelFactory;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class ConnectorService
 * @package Drupal\communico_plus
 *
 */
class ConnectorService {

  /**
   * @var ClientInterface $httpClient
   */
  private ClientInterface $httpClient;

  /**
   * The config factory interface.
   *
   * @var ConfigFactoryInterface $config
   */
  private ConfigFactoryInterface $config;

 /**
  * The state store.
  *
  * @var State $state
  */
 private State $state;

  /**
    * Messenger service.
    *
    * @var LoggerChannelFactory $loggerFactory
    */
 protected LoggerChannelFactory $loggerFactory;

  /**
   * @param ClientInterface $httpClient
   * @param ConfigFactoryInterface $config
   * @param State $state
   * @param LoggerChannelFactory $logger_factory
   */
  public function __construct(
    ClientInterface $httpClient,
    ConfigFactoryInterface $config,
    State $state,
    LoggerChannelFactory $logger_factory) {
    $this->httpClient = $httpClient;
    $this->config = $config;
    $this->state = $state;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   * @throws ContainerExceptionInterface
   * @throws NotFoundExceptionInterface
   */
  public static function create(ContainerInterface $container) {
    return new static(
    $container->get('http_client'),
    $container->get('config.factory'),
    $container->get('state'),
    $container->get('logger.factory'),
    );
  }

  /**
   * Retrieve auth-token based on provided info.
   */
  public function getAuthToken() {
    $auth_header = $this->getAuthHeaders();
    $request_headers = [
      'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
      'Authorization' => $auth_header,
    ];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/token';
    $data = $this->postToCommunico($url, $request_headers, 'grant_type=client_credentials');
    $this->state->set('communico_plus.authHeader', $data['token_type'] . ' ' . $data['access_token']);
    $expire_time = time() + $data['expires_in'];
    $this->setTokenExpire($expire_time);
  }

  /**
   * @return bool
   * Check if authtoken is valid or expired.
   */
  public function isAuthTokenValid() {
    $current_time = time();
    $token_expire = $this->getTokenExpire();
    ($current_time >= $token_expire) ? $return = FALSE : $return = TRUE;
    return $return;
  }

  /**
   * @param $eventId
   * @return false|mixed
   *
   */
  public function getEvent($eventId) {
    if ($this->isAuthTokenValid() == FALSE) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if ($token == FALSE) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = ['fields' => 'eventRegistrationUrl,eventType,eventImage,ages,externalVenueName,types,eventRegistrationUrl'];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/attend/events/'.$eventId;
    $data = $this->getFromCommunico($url, $params, $request_headers);

    return $data;
  }

  /**
   * @param $reservationId
   * @return false|mixed
   * gets reservation info from a reservation id
   */
  public function getReservation($reservationId) {
    if ($this->isAuthTokenValid() == FALSE) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if ($token == FALSE) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = [
      'fields' => 'roomId,reservationId,displayName,locationName,locationId,contactName,contactPhone,contactEmail'
    ];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/reserve/reservations/'.$reservationId;
    $data = $this->getFromCommunico($url, $params, $request_headers);

    return $data;
  }

  /**
   * @return false|mixed
   * gets a list of registrations
   */
  public function getAllReservations($start = FALSE, $limit = FALSE) {
    if (!$start) {
      $start = '0';
    }
    if(!$limit) {
      $limit = '100';
    }
    if ($this->isAuthTokenValid() == FALSE) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if ($token == FALSE) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = [
      'start' => $start,
      'limit' => $limit,
      'fields' => 'roomId,reservationId,displayName,locationName,locationId,contactName,contactPhone,contactEmail'
    ];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/reserve/reservations';
    $data = $this->getFromCommunico($url, $params, $request_headers);

    return $data;
  }

  /**
   * @return false|mixed
   * Gets all room data defined in Communico
   */
  public function getAllRoomNames() {
    if ($this->isAuthTokenValid() == FALSE) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if ($token == FALSE) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = [];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/reserve/rooms';
    $data = $this->getFromCommunico($url, $params, $request_headers);

    return $data;
  }

  /**
   * @return false|mixed
   * Gets all event types defined in Communico
   */
  public function getEventTypes() {
    if ($this->isAuthTokenValid() == FALSE) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if ($token == FALSE) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = [];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/attend/types';
    $data = $this->getFromCommunico($url, $params, $request_headers);

    return $data;
  }

  /**
   * @return false|mixed
   * Gets all event age groups defined in Communico
   */
  public function getEventAgeGroups() {
    if ($this->isAuthTokenValid() == FALSE) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if ($token == FALSE) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = [];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/attend/ages';
    $data = $this->getFromCommunico($url, $params, $request_headers);

    return $data;
  }

  /**
   * @return false|mixed
   * Gets all age groups defined in Communico
   */
  public function getAgeGroups() {
    if ($this->isAuthTokenValid() == FALSE) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if ($token == FALSE) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = [];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/attend/ages';
    $data = $this->getFromCommunico($url, $params, $request_headers);

    return $data;
  }

  /**
   * @return false|mixed
   * Gets all library locations defined in Communico
   */
  public function getLibraryLocations() {
    if ($this->isAuthTokenValid() == FALSE) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if ($token == FALSE) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = [
      'start' => '0',
      'limit' => '40',
      'fields' => 'id,name'
    ];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/client/locations';
    $data = $this->getFromCommunico($url, $params, $request_headers);
    return $data;
  }

  /**
   * @param $start_date
   * @param $end_date
   * @param $type
   * @param $age
   * @param $location
   * @param $limit
   * @return mixed
   *
   */
  public function getEventsFeed($start_date, $end_date, $type, $age, $location, $limit) {
    if ($this->isAuthTokenValid() == FALSE) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if ($token == FALSE) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = [
      'status' => 'published',
      'start' => '0',
      'startDate' => $start_date,
      'endDate' => $end_date,
      'locationId' => $location,
      'limit' => $limit,
      'fields' => 'eventImage,ages,types,eventRegistrationUrl'
    ];
    if($type) {
      $params['types'] = $type;
    }
    if($age) {
      $params['ages'] = $age;
    }
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/attend/events';
    $data = $this->getFromCommunico($url, $params, $request_headers);
    return $data['data']['entries'];
  }

  /**
   * @param $start_date
   * @param $end_date
   * @param $type
   * @param $limit
   * @return mixed
   * Retrieve feed from Communico.
   * @TODO return location from this request for filtering
   */
  public function getFeed($start_date, $end_date, $type, $limit) {
    if ($this->isAuthTokenValid() == FALSE) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if ($token == FALSE) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = [
      'status' => 'published',
      'start' => '0',
      'startDate' => $start_date,
      'endDate' => $end_date,
      'types' => $type,
      'limit' => $limit,
    ];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/attend/events';
    $data = $this->getFromCommunico($url, $params, $request_headers);
    $next_fetch = $this->state->get('communico_plus.nextFetch');
    /* If data is null or cached rely on cache data. */
    if ($data == NULL || $data == FALSE || $next_fetch > time()) {
      $data = $this->state->get('communico_plus.dataCache');
      $data = unserialize($data);

      return $data;
    }
    /* Fetch and set both the cache and next fetch timestamp. */
    $serialized = serialize($data['data']['entries']);
    $this->state->set('communico_plus.dataCache', $serialized);
    $this->state->set('communico_plus.nextFetch', time() + (60 * 5));

    return $data['data']['entries'];
  }

  /**
   * @param $url
   * @param $headers
   * @param null $body
   * @return false|mixed
   * POST request to communico.
   */
  protected function postToCommunico($url, $headers, $body = NULL) {
    $return = FALSE;
    $data = FALSE;
    $options['headers'] = $headers;
    $options['body'] = $body;
    try {
      $response = $this->httpClient->post($url, $options);
      $status = $response->getStatusCode();
      if($status == '200') {
        $data = $response->getBody()->getContents();
      } else {
        $this->loggerFactory->get('communico_plus')
          ->warning('postToCommunico() returned a status '.$status. ' with the response '.$response->getBody()
              ->getContents());
      }
    } catch (RequestException $e) {
      watchdog_exception('communico_plus postToCommunico()', $e);
    }
    if ($data) {
      $return = Json::decode($data);
    }
    return $return;
  }

  /**
   * @param $url
   * @param $params
   * @param $headers
   * @return false|mixed
   * GET request to Communico.
   */
  protected function getFromCommunico($url, $params, $headers) {
    $return = FALSE;
    $data = FALSE;
    try {
      $response = $this->httpClient->get($url, [
        'headers' => $headers,
        'query' => $params,
      ]);
      $status = $response->getStatusCode();
      if($status == '200') {
        $data = $response->getBody()->getContents();
      } else {
        $this->loggerFactory->get('communico_plus')
          ->warning('getFromCommunico() returned a status '.$status. ' with the response '.$response->getBody()
              ->getContents());
      }
    } catch (RequestException $e) {
      watchdog_exception('communico_plus getFromCommunico()', $e);
    }
    if ($data) {
        $return = Json::decode($data);
    }
    return $return;
  }

  /**
   * @param $timestamp
   * Set the token expire date.
   */
  protected function setTokenExpire($timestamp) {
    $this->state->set('communico_plus.token_expire', $timestamp);
  }

  /**
   * @return mixed
   * Get the token expire date.
   */
  protected function getTokenExpire() {
    return $this->state->get('communico_plus.token_expire');
  }

  /**
   * @return array|mixed|null
   * Get communico url.
   */
  protected function getCommunicoUrl() {
    return $this->config->get('communico_plus.settings')->get('url');
  }

  /**
   * @return string
   * Retrieve an auth-header.
   */
  protected function getAuthHeaders() {
    /* auth-header for Communico using 'key:secret' format. */
    $key = $this->config->get('communico_plus.settings')->get('access_key');
    $secret = $this->config->get('communico_plus.settings')->get('secret_key');
    $auth = $key . ':' . $secret;
    $auth = base64_encode($auth);
    return 'Basic ' . $auth;
  }

}
