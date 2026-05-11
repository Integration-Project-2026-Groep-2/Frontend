<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

/**
 * Sessie status waarden — conform common.xsd SessionStatusType.
 */
enum PlanningSessionStatusType: string {
  case ACTIVE    = 'active';
  case CANCELLED = 'cancelled';
  case FULL      = 'full';
  case CONCEPT   = 'concept';
}