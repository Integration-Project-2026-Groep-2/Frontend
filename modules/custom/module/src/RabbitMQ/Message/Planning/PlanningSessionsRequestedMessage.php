<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.sessions.requested
 * Routing:  frontend.sessions.requested
 * Element:  FrontendSessionsRequested
 *
 * Leeg bericht — vraagt alle sessies op bij planning.
 */
final class PlanningSessionsRequestedMessage extends Planning {

  public function toXml(): string {
    return (new SimpleXMLElement('<FrontendSessionsRequested/>'))->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.sessions.requested';
  }

  public function getType(): string {
    return 'planning_sessions_requested';
  }

}
