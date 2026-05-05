<?php

namespace Drupal\hello_world\RabbitMQ\Publisher;

use Drupal\hello_world\RabbitMQ\Message\CompanyCreatedMessage;

class CompanyPublisher {

  private $client;

  public function __construct(RabbitMQClient $client) {
    $this->client = $client;
  }

  /**
   * Publish company created event.
   */
  public function publishCompanyCreated(array $payload): void {
    $msg = new CompanyCreatedMessage($payload);
    $this->client->publish($msg);
  }
}