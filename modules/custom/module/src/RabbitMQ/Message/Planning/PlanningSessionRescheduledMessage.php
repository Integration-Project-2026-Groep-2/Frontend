<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.session.rescheduled
 * Routing:  frontend.session.rescheduled
 * Element:  SessionRescheduled
 */
final class PlanningSessionRescheduledMessage extends Planning {

  public function __construct(
    private readonly string  $sessionId,
    private readonly string  $sessionName,
    private readonly string  $oldDate,
    private readonly string  $oldStartTime,
    private readonly string  $newDate,
    private readonly string  $newStartTime,
    private readonly ?string $oldEndTime  = NULL,
    private readonly ?string $newEndTime  = NULL,
    private readonly ?string $newLocation = NULL,
    private readonly ?string $reason      = NULL,
    private readonly ?string $timestamp   = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<SessionRescheduled/>');
    $xml->addChild('sessionId',    $this->sessionId);
    $xml->addChild('sessionName',  htmlspecialchars($this->sessionName));
    $xml->addChild('oldDate',      $this->oldDate);
    $xml->addChild('oldStartTime', $this->oldStartTime);
    
    if ($this->oldEndTime !== NULL) {
      $xml->addChild('oldEndTime', $this->oldEndTime);
    }
    
    $xml->addChild('newDate',      $this->newDate);
    $xml->addChild('newStartTime', $this->newStartTime);
    
    if ($this->newEndTime !== NULL) {
      $xml->addChild('newEndTime', $this->newEndTime);
    }
    if ($this->newLocation !== NULL) {
      $xml->addChild('newLocation', htmlspecialchars($this->newLocation));
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
    return 'frontend.session.rescheduled';
  }

  public function getType(): string {
    return 'planning_session_rescheduled';
  }

}
