<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.locations.requested
 * Routing:  frontend.locations.requested
 * Element:  FrontendLocationsRequested
 */
final class PlanningLocationsRequestedMessage extends Planning {

  public function toXml(): string {
    return (new SimpleXMLElement('<FrontendLocationsRequested/>'))->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.locations.requested';
  }

  public function getType(): string {
    return 'planning_locations_requested';
  }

}
