<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.location.created
 * Routing:  frontend.location.created
 * Element:  FrontendLocationCreated
 */
final class PlanningLocationCreatedMessage extends Planning {

  public function __construct(
    private readonly string  $locationId,
    private readonly string  $roomName,
    private readonly int     $capacity,
    private readonly ?string $address = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<LocationCreated/>');
    if ($this->locationId !== NULL) {
      $xml->addChild('locationId', $this->locationId);
    }
    $xml->addChild('roomName', htmlspecialchars($this->roomName));
    $xml->addChild('capacity', (string) $this->capacity);

    if ($this->address !== NULL) {
      $xml->addChild('address', htmlspecialchars($this->address));
    }
    
    $xml->addChild('status', 'beschikbaar');
    $xml->addChild('timestamp', (new \DateTime())->format(\DateTime::ATOM));

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.location.created';
  }

  public function getType(): string {
    return 'planning_location_created';
  }

}