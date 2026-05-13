<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.location.updated
 * Routing:  frontend.location.updated
 * Element:  FrontendLocationUpdated
 *
 * status: beschikbaar | onbeschikbaar | maintenance | reserved
 */
final class PlanningLocationUpdatedMessage extends Planning {

  public function __construct(
    private readonly string  $locationId,
    private readonly string  $roomName,
    private readonly int     $capacity,
    private readonly string  $status,
    private readonly ?string $address = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<LocationUpdated/>');
    $xml->addChild('locationId', $this->locationId);
    $xml->addChild('roomName',   htmlspecialchars($this->roomName));
    $xml->addChild('capacity',   (string) $this->capacity);

    if ($this->address !== NULL) {
      $xml->addChild('address', htmlspecialchars($this->address));
    }

    $xml->addChild('status', $this->status);
    $xml->addChild('timestamp', (new \DateTime())->format(\DateTime::ATOM));

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.location.updated';
  }

  public function getType(): string {
    return 'planning_location_updated';
  }

}
