<?php

namespace Intercom;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;

function stream_for($resource = '', array $options = []) {
    if (is_scalar($resource)) {
        $stream = fopen('php://temp', 'r+');
        if ($resource !== '') {
            fwrite($stream, $resource);
            fseek($stream, 0);
        }
        return new Stream($stream, $options);
    }
    switch (gettype($resource)) {
        case 'resource':
            return new Stream($resource, $options);
        case 'object':
            if ($resource instanceof StreamInterface) {
                return $resource;
            } elseif ($resource instanceof \Iterator) {
                return new PumpStream(function () use ($resource) {
                    if (!$resource->valid()) {
                        return false;
                    }
                    $result = $resource->current();
                    $resource->next();
                    return $result;
                }, $options);
            } elseif (method_exists($resource, '__toString')) {
                return stream_for((string) $resource, $options);
            }
            break;
        case 'NULL':
            return new Stream(fopen('php://temp', 'r+'), $options);
    }
    if (is_callable($resource)) {
        return new PumpStream($resource, $options);
    }
    throw new \InvalidArgumentException('Invalid resource type: ' . gettype($resource));
}

class IntercomClient {

  /** @var Client $http_client */
  private $http_client;

  /** @var string API user authentication */
  protected $usernamePart;

  /** @var string API password authentication */
  protected $passwordPart;

  /** @var IntercomUsers $users */
  public $users;

  /** @var IntercomEvents $events */
  public $events;

  /** @var IntercomCompanies $companies */
  public $companies;

  /** @var IntercomMessages $messages */
  public $messages;

  /** @var IntercomConversations $conversations */
  public $conversations;

  /** @var IntercomLeads $leads */
  public $leads;

  /** @var IntercomAdmins $admins */
  public $admins;

  /** @var IntercomTags $tags */
  public $tags;

  /** @var IntercomCounts $counts */
  public $counts;

  /** @var IntercomBulk $bulk */
  public $bulk;

  public function __construct($usernamePart, $passwordPart)
  {
    $this->setDefaultClient();
    $this->users = new IntercomUsers($this);
    $this->events = new IntercomEvents($this);
    $this->companies = new IntercomCompanies($this);
    $this->messages = new IntercomMessages($this);
    $this->conversations = new IntercomConversations($this);
    $this->leads = new IntercomLeads($this);
    $this->admins = new IntercomAdmins($this);
    $this->tags = new IntercomTags($this);
    $this->counts = new IntercomCounts($this);
    $this->bulk = new IntercomBulk($this);

    $this->usernamePart = $usernamePart;
    $this->passwordPart = $passwordPart;
  }

  private function setDefaultClient()
  {
    $this->http_client = new Client();
  }

  public function setClient($client)
  {
    $this->http_client = $client;
  }

  public function post($endpoint, $json)
  {
    $response = $this->http_client->request('POST', "https://api.intercom.io/$endpoint", [
      'json' => $json,
      'auth' => $this->getAuth(),
      'headers' => [
        'Accept' => 'application/json'
      ]
    ]);
    return $this->handleResponse($response);
  }

  public function delete($endpoint, $json)
  {
    $response = $this->http_client->request('DELETE', "https://api.intercom.io/$endpoint", [
      'json' => $json,
      'auth' => $this->getAuth(),
      'headers' => [
        'Accept' => 'application/json'
      ]
    ]);
    return $this->handleResponse($response);
  }

  public function get($endpoint, $query)
  {
    $response = $this->http_client->request('GET', "https://api.intercom.io/$endpoint", [
      'query' => $query,
      'auth' => $this->getAuth(),
      'headers' => [
        'Accept' => 'application/json'
      ]
    ]);
    return $this->handleResponse($response);
  }

  public function nextPage($pages)
  {
    $response = $this->http_client->request('GET', $pages['next'], [
      'auth' => $this->getAuth(),
      'headers' => [
        'Accept' => 'application/json'
      ]
    ]);
    return $this->handleResponse($response);
  }

  public function getAuth()
  {
    return [$this->usernamePart, $this->passwordPart];
  }

  private function handleResponse(Response $response){
    $stream = stream_for($response->getBody());
    $data = json_decode($stream->getContents());
    return $data;
  }
}
