<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.session.updated
 * Routing:  frontend.session.updated
 * Element:  FrontendSessionUpdated
 */
final class PlanningSessionUpdatedMessage extends Planning {

  public function __construct(
    private readonly string  $sessionId,
    private readonly string  $title,
    private readonly string  $date,
    private readonly string  $startTime,
    private readonly string  $endTime,
    private readonly int     $capacity,
    private readonly ?string $locationId = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<FrontendSessionUpdated/>');
    $xml->addChild('sessionId', $this->sessionId);
    $xml->addChild('title',     htmlspecialchars($this->title));
    $xml->addChild('date',      $this->date);
    $xml->addChild('startTime', $this->startTime);
    $xml->addChild('endTime',   $this->endTime);
    $xml->addChild('capacity',  (string) $this->capacity);

    if ($this->locationId !== NULL) {
      $xml->addChild('locationId', $this->locationId);
    }

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.session.updated';
  }

  public function getType(): string {
    return 'planning_session_updated';
  }
}