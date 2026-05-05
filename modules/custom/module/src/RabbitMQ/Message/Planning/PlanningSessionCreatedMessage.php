<?php

namespace Drupal\hello_world\RabbitMQ\Message;

use DateTimeImmutable;
use SimpleXMLElement;

final class PlanningCreatedMessage extends Planning implements MessageInterface
{
    public function __construct(
        string $sessionId,
        private readonly string $title,
        private readonly DateTimeImmutable $date,
        private readonly DateTimeImmutable $startTime,
        private readonly DateTimeImmutable $endTime,
        private readonly string $location,
        private readonly PlanningSessionStatusType $status,
        private readonly int $capacity,
        private readonly ?string $icsData,
        private readonly DateTimeImmutable $timestamp,
    ) {
        parent::__construct($sessionId, $timestamp);
    }

    public function toXml(): string
    {
        $xml = new SimpleXMLElement("<SessionCreated/>");

        $xml->addChild("sessionId", $this->sessionId);
        $xml->addChild("title", $this->title);
        $xml->addChild("date", $this->date->format("Y-m-d"));
        $xml->addChild("startTime", $this->startTime->format("H:i:s"));
        $xml->addChild("endTime", $this->endTime->format("H:i:s"));
        $xml->addChild("location", $this->location);
        $xml->addChild("status", $this->status);
        $xml->addChild("capacity", (string) $this->capacity);

        if ($this->icsData !== null) {
            $xml->addChild("icsData", $this->icsData);
        }

        $xml->addChild(
            "timestamp",
            $this->timestamp->format(DateTimeImmutable::ATOM),
        );

        return $xml->asXML();
    }

    public function getRoutingKey(): string
    {
        return "planning.session.created";
    }

    public function getType(): string
    {
        return "planning.session.created";
    }
}
