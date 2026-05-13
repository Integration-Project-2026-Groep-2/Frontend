<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.session.cancelled
 * Routing:  frontend.session.cancelled
 * Element:  SessionCancelled
 */
final class PlanningSessionCancelledMessage extends Planning {

  public function __construct(
    private readonly string  $sessionId,
    private readonly string  $sessionName,
    private readonly ?string $status    = NULL,
    private readonly ?string $reason    = NULL,
    private readonly ?string $timestamp = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<SessionCancelled/>');
    $xml->addChild('sessionId',   $this->sessionId);
    $xml->addChild('sessionName', htmlspecialchars($this->sessionName));

    if ($this->status !== NULL) {
      $xml->addChild('status', $this->status);
    }
    if ($this->reason !== NULL) {
      $xml->addChild('reason', htmlspecialchars($this->reason));
    }
    if ($this->timestamp !== NULL) {
      $xml->addChild('timestamp', $this->timestamp);
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