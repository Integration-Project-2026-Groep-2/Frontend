<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.session.created
 * Routing:  frontend.session.created
 * Element:  SessionCreated
 */
final class PlanningSessionCreatedMessage extends Planning {

  public function __construct(
    private readonly string  $title,
    private readonly string  $date,       // Y-m-d
    private readonly string  $startTime,  // H:i:s
    private readonly string  $endTime,    // H:i:s
    private readonly int     $capacity,
    private readonly ?string $locationId = NULL,
    private readonly ?string $location   = NULL,
    private readonly ?string $speakerId  = NULL,
    private readonly ?string $status     = NULL,
    private readonly ?string $timestamp  = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<SessionCreated/>');
    $xml->addChild('title',    htmlspecialchars($this->title));
    $xml->addChild('date',     $this->date);
    $xml->addChild('startTime', $this->startTime);
    $xml->addChild('endTime',   $this->endTime);
    $xml->addChild('capacity',  (string) $this->capacity);

    if ($this->locationId !== NULL && self::isValidUuid($this->locationId)) {
      $xml->addChild('locationId', $this->locationId);
    }
    if ($this->location !== NULL) {
      $xml->addChild('location', htmlspecialchars($this->location));
    }
    if ($this->speakerId !== NULL && self::isValidUuid($this->speakerId)) {
      $xml->addChild('speakerId', $this->speakerId);
    }
    if ($this->status !== NULL) {
      $xml->addChild('status', $this->status);
    }
    if ($this->timestamp !== NULL) {
      $xml->addChild('timestamp', $this->timestamp);
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