<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use DateTimeImmutable;
use SimpleXMLElement;

final class PlanningSessionUpdatedMessage extends Planning {

  public function __construct(
    string $sessionId,
    private readonly ?string            $title,
    private readonly ?DateTimeImmutable $date,
    private readonly ?DateTimeImmutable $startTime,
    private readonly ?DateTimeImmutable $endTime,
    private readonly ?int               $capacity,
    private readonly ?string            $locationId,
    DateTimeImmutable $timestamp,
  ) {
    parent::__construct($sessionId, $timestamp);
  }

  public function toXml(): string {
    $xml = new SimpleXMLElement('<SessionUpdated/>');
    $xml->addChild('sessionId', $this->sessionId);

    if ($this->title !== NULL) {
      $xml->addChild('title', htmlspecialchars($this->title));
    }
    if ($this->date !== NULL) {
      $xml->addChild('date', $this->date->format('Y-m-d'));
    }
    if ($this->startTime !== NULL) {
      $xml->addChild('startTime', $this->startTime->format('H:i:s'));
    }
    if ($this->endTime !== NULL) {
      $xml->addChild('endTime', $this->endTime->format('H:i:s'));
    }
    if ($this->capacity !== NULL) {
      $xml->addChild('capacity', (string) $this->capacity);
    }
    if ($this->locationId !== NULL) {
      $xml->addChild('locationId', htmlspecialchars($this->locationId));
    }

    $xml->addChild('timestamp', $this->timestamp->format(DateTimeImmutable::ATOM));

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.session.updated';
  }

  public function getType(): string {
    return 'planning.session.updated';
  }

}