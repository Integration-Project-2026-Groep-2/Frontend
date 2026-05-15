<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.session.updated
 * Routing:  frontend.session.updated
 * Element:  SessionUpdated
 */
final class PlanningSessionUpdatedMessage extends Planning {

  public function __construct(
    private readonly string  $sessionId,
    private readonly string  $sessionName,
    private readonly ?string $newDescription = NULL,
    private readonly string  $changeType,
    private readonly ?string $newTime      = NULL,
    private readonly ?string $newStartTime = NULL,
    private readonly ?string $newEndTime   = NULL,
    private readonly ?string $newLocation  = NULL,
    private readonly ?string $newLocationId = NULL,
    private readonly ?int    $newCapacity   = NULL,
    private readonly ?string $newStatus     = NULL,
    private readonly ?string $timestamp     = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<SessionUpdated/>');
    if (!self::isValidUuid($this->sessionId)) {
        throw new \InvalidArgumentException("Invalid UUID for sessionId: " . $this->sessionId);
    }
    $xml->addChild('sessionId',   $this->sessionId);
    $xml->addChild('sessionName', htmlspecialchars($this->sessionName));
    if ($this->newDescription !== NULL) {
      $xml->addChild('newDescription', htmlspecialchars($this->newDescription));
    }
    $xml->addChild('changeType',  $this->changeType);

    if ($this->newTime !== NULL) {
      $xml->addChild('newTime', $this->newTime);
    }
    if ($this->newStartTime !== NULL) {
      $xml->addChild('newStartTime', $this->newStartTime);
    }
    if ($this->newEndTime !== NULL) {
      $xml->addChild('newEndTime', $this->newEndTime);
    }
    if ($this->newLocation !== NULL) {
      $xml->addChild('newLocation', htmlspecialchars($this->newLocation));
    }
    if ($this->newLocationId !== NULL && self::isValidUuid($this->newLocationId)) {
      $xml->addChild('newLocationId', $this->newLocationId);
    }
    if ($this->newCapacity !== NULL) {
      $xml->addChild('newCapacity', (string) $this->newCapacity);
    }
    if ($this->newStatus !== NULL) {
      $xml->addChild('newStatus', $this->newStatus);
    }
    if ($this->timestamp !== NULL) {
      $xml->addChild('timestamp', $this->timestamp);
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