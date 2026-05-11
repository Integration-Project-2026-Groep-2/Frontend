<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.session.cancelled
 * Routing:  frontend.session.cancelled
 * Element:  FrontendSessionCancelled
 */
final class PlanningSessionCancelledMessage extends Planning {

  public function __construct(
    private readonly string  $sessionId,
    private readonly ?string $reason = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<FrontendSessionCancelled/>');
    $xml->addChild('sessionId', $this->sessionId);

    if ($this->reason !== NULL) {
      $xml->addChild('reason', htmlspecialchars($this->reason));
    }

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.session.cancelled';
  }

  public function getType(): string {
    return 'planning_session_cancelled';
  }
}