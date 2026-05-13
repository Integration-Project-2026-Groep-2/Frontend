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
    private readonly string  $changeType,
    private readonly ?string $newTime     = NULL,
    private readonly ?string $newLocation  = NULL,
    private readonly ?string $timestamp    = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<SessionUpdated/>');
    $xml->addChild('sessionId',   $this->sessionId);
    $xml->addChild('sessionName', htmlspecialchars($this->sessionName));
    $xml->addChild('changeType',  $this->changeType);

    if ($this->newTime !== NULL) {
      $xml->addChild('newTime', $this->newTime);
    }
    if ($this->newLocation !== NULL) {
      $xml->addChild('newLocation', htmlspecialchars($this->newLocation));
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