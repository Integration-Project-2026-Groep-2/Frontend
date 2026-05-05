<?php

namespace Drupal\hello_world\RabbitMQ\Message;

class CompanyCreatedMessage implements MessageInterface {

  private $payload;

  public function __construct(array $payload) {
    $this->payload = $payload;
  }

  public function getPayload(): array {
    return $this->payload;
  }

  public function getRoutingKey(): string {
    return 'crm.frontend.company.created';
  }

  public function getExchange(): string {
    return 'user.topic';
  }
}