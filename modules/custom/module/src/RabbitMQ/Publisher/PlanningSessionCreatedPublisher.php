<?php

namespace Drupal\hello_world\RabbitMQ\Publisher;

use Drupal\hello_world\RabbitMQ\Message\Planning\PlanningSessionCreatedMessage;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;

/**
 * Publishes planning.session.created (frontend.session.created) naar RabbitMQ.
 */
class PlanningSessionCreatedPublisher {

  public function __construct(
    private readonly PlanningSessionCreatedMessage $message,
  ) {}

  public function publish(): void {
    try {
      $client = RabbitMQClient::fromEnv();
      $client->publish($this->message);
    }
    catch (\Exception $e) {
      \Drupal::logger('rabbitmq')->error(
        'PlanningSessionCreatedPublisher mislukt: @err',
        ['@err' => $e->getMessage()]
      );
    }
  }

}
