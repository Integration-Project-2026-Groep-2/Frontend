<?php

namespace Drupal\hello_world\RabbitMQ\Message;

enum PlanningSessionStatusType
{
    case ACTIVE;
    case CANCELLED;
    case FULL;
    case CONCEPT;
}
