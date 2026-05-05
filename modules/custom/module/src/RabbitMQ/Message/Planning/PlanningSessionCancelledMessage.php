<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use DateTimeImmutable;
use SimpleXMLElement;

final class PlanningSessionCancelledMessage extends Planning {

  public function __construct(
    string $sessionId,
    private readonly ?string $reason,
    DateTimeImmutable $timestamp,
  ) {
    parent::__construct($sessionId, $timestamp);
  }

  public function toXml(): string {
    $xml = new SimpleXMLElement('<SessionCancelled/>');
    $xml->addChild('sessionId', $this->sessionId);

    if ($this->reason !== NULL) {
      $xml->addChild('reason', htmlspecialchars($this->reason));
    }

    $xml->addChild('timestamp', $this->timestamp->format(DateTimeImmutable::ATOM));

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.session.cancelled';
  }

  public function getType(): string {
    return 'planning.session.cancelled';
  }

}