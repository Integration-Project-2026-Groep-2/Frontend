<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.session.created
 * Routing:  frontend.session.created
 * Element:  FrontendSessionCreated
 *
 * speakerId is een UUID — planning kent die toe aan een speaker.
 * sessionId wordt door planning gegenereerd — niet meegestuurd.
 */
final class PlanningSessionCreatedMessage extends Planning {

  public function __construct(
    private readonly string  $title,
    private readonly string  $date,       // Y-m-d
    private readonly string  $startTime,  // H:i:s
    private readonly string  $endTime,    // H:i:s
    private readonly int     $capacity,
    private readonly ?string $locationId = NULL,
    private readonly ?string $speakerId  = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<FrontendSessionCreated/>');
    $xml->addChild('title',    htmlspecialchars($this->title));
    $xml->addChild('date',     $this->date);
    $xml->addChild('startTime', $this->startTime);
    $xml->addChild('endTime',   $this->endTime);
    $xml->addChild('capacity',  (string) $this->capacity);

    if ($this->locationId !== NULL) {
      $xml->addChild('locationId', $this->locationId);
    }
    if ($this->speakerId !== NULL) {
      $xml->addChild('speakerId', $this->speakerId);
    }

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.session.created';
  }

  public function getType(): string {
    return 'planning_session_created';
  }
}