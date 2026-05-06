<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use DateTimeImmutable;

/**
 * Base class voor alle Planning messages.
 */
abstract class Planning implements \Drupal\hello_world\RabbitMQ\Message\MessageInterface {

  public function __construct(
    protected readonly string            $sessionId,
    protected readonly DateTimeImmutable $timestamp,
  ) {}

}