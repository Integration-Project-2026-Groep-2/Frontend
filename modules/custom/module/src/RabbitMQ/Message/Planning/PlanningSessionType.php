<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

enum PlanningSessionStatusType: string {
  case ACTIVE    = 'ACTIVE';
  case CANCELLED = 'CANCELLED';
  case FULL      = 'FULL';
  case CONCEPT   = 'CONCEPT';
}