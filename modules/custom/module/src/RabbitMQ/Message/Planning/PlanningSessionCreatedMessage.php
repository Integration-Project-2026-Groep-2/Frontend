<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use DateTimeImmutable;
use SimpleXMLElement;

/**
 * Contract: planning.session.created
 * Queue:    planning.session.created
 * Routing:  frontend.session.created
 *
 * sessionId wordt toegewezen door planning — frontend stuurt het niet mee.
 */
final class PlanningSessionCreatedMessage extends Planning {

  public function __construct(
    private readonly string            $title,
    private readonly DateTimeImmutable $date,
    private readonly DateTimeImmutable $startTime,
    private readonly DateTimeImmutable $endTime,
    private readonly string            $locationId,
    private readonly int               $capacity,
    private readonly ?string           $speakerId,
    DateTimeImmutable                  $timestamp,
  ) {
    // Geen sessionId — planning genereert die.
    parent::__construct('', $timestamp);
  }

  public function toXml(): string {
    $xml = new SimpleXMLElement('<SessionCreated/>');
    $xml->addChild('title',      htmlspecialchars($this->title));
    $xml->addChild('date',       $this->date->format('Y-m-d'));
    $xml->addChild('startTime',  $this->startTime->format('H:i:s'));
    $xml->addChild('endTime',    $this->endTime->format('H:i:s'));
    $xml->addChild('locationId', htmlspecialchars($this->locationId));
    $xml->addChild('capacity',   (string) $this->capacity);

    if ($this->speakerId !== NULL) {
      $xml->addChild('speakerId', htmlspecialchars($this->speakerId));
    }

    $xml->addChild('timestamp', $this->timestamp->format(DateTimeImmutable::ATOM));

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.session.created';
  }

  public function getType(): string {
    return 'planning.session.created';
  }

}