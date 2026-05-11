<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.location.deleted
 * Routing:  frontend.location.deleted
 * Element:  FrontendLocationDeleted
 */
final class PlanningLocationDeletedMessage extends Planning {

  public function __construct(
    private readonly string $locationId,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<FrontendLocationDeleted/>');
    $xml->addChild('locationId', $this->locationId);
    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.location.deleted';
  }

  public function getType(): string {
    return 'planning_location_deleted';
  }

}
