<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use Drupal\hello_world\RabbitMQ\Message\MessageInterface;

/**
 * Base class voor alle Planning messages.
 */
abstract class Planning implements MessageInterface {
  /**
   * Validates if a string is a valid UUID.
   */
  public static function isValidUuid(string $uuid): bool {
    return (bool) preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $uuid)
      || $uuid === '00000000-0000-0000-0000-000000000000'
      || $uuid === 'ffffffff-ffff-ffff-ffff-ffffffffffff';
  }
}