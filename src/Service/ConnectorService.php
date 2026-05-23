<?php

namespace Drupal\communico_plus\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\State\State;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * The random frontpage connector service class.
 *
 * \Drupal\communico_plus\Service\ConnectorService.
 */
class ConnectorService {

  /**
   * The Drupal http client interface.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private $httpClient;

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\State
   */
  private $state;

  /**
   * Drupal logger channel factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The ConnectorService constructor.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The Drupal http client interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory interface.
   * @param \Drupal\Core\State\State $state
   *   The state store.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The Drupal logger channel factory service.
   */
  public function __construct(
    ClientInterface $httpClient,
    ConfigFactoryInterface $config,
    State $state,
    LoggerChannelFactory $logger_factory,
  ) {
    $this->httpClient = $httpClient;
    $this->config = $config;
    $this->state = $state;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * The getAuthToken function.
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
   * The isAuthTokenValid function.
   *
   * @return bool
   *   Checks if the auth token is still valid.
   */
  public function isAuthTokenValid() {
    $current_time = time();
    $token_expire = $this->getTokenExpire();
    ($current_time >= $token_expire) ? $return = FALSE : $return = TRUE;
    return $return;
  }

  /**
   * The getEvent function.
   *
   * @param string $eventId
   *   The event id to retrieve.
   *
   * @return false|mixed
   *   Retrieves an event from Communico based on the event id.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getEvent($eventId) {
    if (!$this->isAuthTokenValid()) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if (!$token) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];

    $params = [
      'fields' => 'eventRegistrationUrl,eventType,eventImage,ages,externalVenueName,types,eventRegistrationUrl',
    ];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/attend/events/' . $eventId;

    return $this->getFromCommunico($url, $params, $request_headers);
  }

  /**
   * The getReservation function.
   *
   * @param string $reservationId
   *   The reservation id to retrieve.
   *
   * @return array|false|mixed
   *   Retrieves a reservation from Communico based on the reservation id.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getReservation($reservationId) {
    if (!$this->isAuthTokenValid()) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if (!$token) {
      $this->getAuthToken();
      $token = $this->state->get('communico_plus.authHeader');
    }
    $request_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'Authorization' => $token,
    ];
    $params = [
      'fields' => 'roomId,reservationId,displayName,locationName,locationId,contactName,contactPhone,contactEmail',
    ];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/reserve/reservations/' . $reservationId;

    return $this->getFromCommunico($url, $params, $request_headers);
  }

  /**
   * The getAllReservations function.
   *
   * @param bool $start
   *   The starting point for the reservations to retrieve.
   * @param bool $limit
   *   The maximum number of reservations to retrieve.
   */
  public function getAllReservations($start = FALSE, $limit = FALSE) {
    if (!$start) {
      $start = '0';
    }
    if (!$limit) {
      $limit = '100';
    }
    if (!$this->isAuthTokenValid()) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if (!$token) {
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
      'fields' => 'roomId,reservationId,displayName,locationName,locationId,contactName,contactPhone,contactEmail',
    ];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/reserve/reservations';

    return $this->getFromCommunico($url, $params, $request_headers);
  }

  /**
   * The getAllRoomNames function.
   *
   * @return array|false|mixed
   *   Gets all room names defined in Communico.
   */
  public function getAllRoomNames() {
    if (!$this->isAuthTokenValid()) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if (!$token) {
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

    return $this->getFromCommunico($url, $params, $request_headers);
  }

  /**
   * The getEventTypes function.
   *
   * @return array|false|mixed
   *   Gets all event types defined in Communico.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getEventTypes() {
    if (!$this->isAuthTokenValid()) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if (!$token) {
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

    return $this->getFromCommunico($url, $params, $request_headers);
  }

  /**
   * The getEventAgeGroups function.
   *
   * @return array|false|mixed
   *   Gets all event age groups defined in Communico.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getEventAgeGroups() {
    if (!$this->isAuthTokenValid()) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if (!$token) {
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

    return $this->getFromCommunico($url, $params, $request_headers);
  }

  /**
   * The getAgeGroups function.
   *
   * @return array|false|mixed
   *   Gets all age groups defined in Communico.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getAgeGroups() {
    if (!$this->isAuthTokenValid()) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if (!$token) {
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

    return $this->getFromCommunico($url, $params, $request_headers);
  }

  /**
   * The getLibraryLocations function.
   *
   * @return array|false|mixed
   *   Gets all library locations defined in Communico.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getLibraryLocations() {
    if (!$this->isAuthTokenValid()) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if (!$token) {
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
      'fields' => 'id,name',
    ];
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/client/locations';
    return $this->getFromCommunico($url, $params, $request_headers);
  }

  /**
   * The getEventsFeed function.
   *
   * @param string $start_date
   *   The starting date for the feed in ISO format.
   * @param string $end_date
   *   The ending date for the feed in ISO format.
   * @param string $type
   *   The type of events to retrieve.
   * @param string $age
   *   The age group of events to retrieve.
   * @param string $location
   *   The location of events to retrieve.
   * @param int $limit
   *   The maximum number of events to retrieve.
   *
   * @return mixed
   *   Retrieves a feed of events from Communico based on the provided filters.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getEventsFeed($start_date, $end_date, $type, $age, $location, $limit) {
    if (!$this->isAuthTokenValid()) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if (!$token) {
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
      'locationId' => [$location],
      'limit' => $limit,
      'fields' => 'eventImage,ages,types,eventRegistrationUrl',
    ];
    if ($type) {
      $params['types'] = $type;
    }
    if ($age) {
      $params['ages'] = $age;
    }
    $url = $this->getCommunicoUrl();
    $url = $url . '/v3/attend/events';
    $data = $this->getFromCommunico($url, $params, $request_headers);
    return $data['data']['entries'];
  }

  /**
   * The getFeed function.
   *
   * @param string $start_date
   *   The starting date for the feed in ISO format.
   * @param string $end_date
   *   The ending date for the feed in ISO format.
   * @param string $type
   *   The type of events to retrieve.
   * @param int $limit
   *   The maximum number of events to retrieve.
   *
   * @return mixed
   *   Retrieves a feed of events from Communico.
   *
   * @todo return location from this request for filtering
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getFeed($start_date, $end_date, $type, $limit) {
    if (!$this->isAuthTokenValid()) {
      $this->getAuthToken();
    }
    $token = $this->state->get('communico_plus.authHeader');
    if (!$token) {
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
    if (!$data || $next_fetch > time()) {
      $cached = $this->state->get('communico_plus.dataCache');
      // Try JSON first (new format). Fallback to safe unserialize for legacy.
      if ($cached !== NULL) {
        $decoded = json_decode($cached, TRUE);
        if ($decoded === NULL && $cached !== 'null') {
          // Legacy: allow only scalar/array objects from serialized data.
          $decoded = @unserialize($cached, ['allowed_classes' => FALSE]);
        }
      }
      return $decoded ?? [];
    }
    /* Fetch and set both the cache and next fetch timestamp. */
    $payload = json_encode($data['data']['entries']);
    $this->state->set('communico_plus.dataCache', $payload);
    $this->state->set('communico_plus.nextFetch', time() + (60 * 5));

    return $data['data']['entries'];
  }

  /**
   * The postToCommunico function.
   *
   * @param string $url
   *   The URL to send the POST request to.
   * @param string $headers
   *   The headers to include in the POST request.
   * @param string $body
   *   The body to include in the POST request.
   *
   * @return false|mixed
   *   Retrieves data from Communico after sending a POST request.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function postToCommunico($url, $headers, $body = NULL) {
    $return = FALSE;
    $data = FALSE;
    $options['headers'] = $headers;
    $options['body'] = $body;
    try {
      $response = $this->httpClient->post($url, $options);
      $status = $response->getStatusCode();
      if ($status == '200') {
        $data = $response->getBody()->getContents();
      }
      else {
        $this->loggerFactory->get('communico_plus')
          ->warning('postToCommunico() returned a status ' . $status . ' with the response ' . $response->getBody()
            ->getContents());
      }
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('communico_plus')
        ->error($e);
    }
    if ($data) {
      $return = Json::decode($data);
    }
    return $return;
  }

  /**
   * The getFromCommunico function.
   *
   * @param string $url
   *   The URL to send the GET request to.
   * @param array $params
   *   The query parameters to include in the GET request.
   * @param array $headers
   *   The headers to include in the GET request.
   *
   * @return false|mixed
   *   Retrieves data from Communico.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
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
      if ($status == '200') {
        $data = $response->getBody()->getContents();
      }
      else {
        $this->loggerFactory->get('communico_plus')
          ->warning('getFromCommunico() returned a status ' . $status . ' with the response ' . $response->getBody()
            ->getContents());
      }
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('communico_plus')
        ->error($e);
    }
    if ($data) {
      $return = Json::decode($data);
    }
    return $return;
  }

  /**
   * The setTokenExpire function.
   *
   * @param string $timestamp
   *   The timestamp to set as the token expire date.
   */
  protected function setTokenExpire($timestamp) {
    $this->state->set('communico_plus.token_expire', $timestamp);
  }

  /**
   * The getTokenExpire function.
   *
   * @return mixed
   *   Get the token expire date.
   */
  protected function getTokenExpire() {
    return $this->state->get('communico_plus.token_expire');
  }

  /**
   * The getCommunicoUrl function.
   *
   * @return array|mixed|null
   *   Get communico url.
   */
  protected function getCommunicoUrl() {
    return $this->config->get('communico_plus.settings')->get('url');
  }

  /**
   * The getAuthHeaders function.
   *
   * @return string
   *   Retrieve  an auth-header.
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
