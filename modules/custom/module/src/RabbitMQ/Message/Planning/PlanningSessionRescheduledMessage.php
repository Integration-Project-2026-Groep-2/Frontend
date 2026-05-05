<?php

namespace Drupal\hello_world\RabbitMQ\Message;

use DateTimeImmutable;
use SimpleXMLElement;

final class PlanningRescheduled extends Planning implements MessageInterface
{
    private string $sessionId;
    private string $sessionName;

    private DateTimeImmutable $oldDate;
    private DateTimeImmutable $oldStartTime;
    private DateTimeImmutable $oldEndTime;

    private DateTimeImmutable $newDate;
    private DateTimeImmutable $newStartTime;
    private DateTimeImmutable $newEndTime;

    private ?string $newLocation;
    private ?string $reason;

    /** @var string[]|null */
    private ?array $participantIds;

    private ?string $icsData;

    private DateTimeImmutable $timestamp;

    public function __construct(
        string $sessionId,
        string $sessionName,
        DateTimeImmutable $oldDate,
        DateTimeImmutable $oldStartTime,
        DateTimeImmutable $oldEndTime,
        DateTimeImmutable $newDate,
        DateTimeImmutable $newStartTime,
        DateTimeImmutable $newEndTime,
        ?string $newLocation,
        ?string $reason,
        ?array $participantIds,
        ?string $icsData,
        DateTimeImmutable $timestamp,
    ) {
        $this->sessionName = $sessionName;

        $this->oldDate = $oldDate;
        $this->oldStartTime = $oldStartTime;
        $this->oldEndTime = $oldEndTime;

        $this->newDate = $newDate;
        $this->newStartTime = $newStartTime;
        $this->newEndTime = $newEndTime;

        $this->newLocation = $newLocation;
        $this->reason = $reason;
        $this->participantIds = $participantIds;
        $this->icsData = $icsData;

        parent::__construct($timestamp, $sessionId);
    }

    public function toXml(): string
    {
        $xml = new SimpleXMLElement("<SessionRescheduled/>");

        $xml->addChild("sessionId", $this->sessionId);
        $xml->addChild("sessionName", $this->sessionName);

        $xml->addChild("oldDate", $this->oldDate->format("Y-m-d"));
        $xml->addChild("oldStartTime", $this->oldStartTime->format("H:i:s"));
        $xml->addChild("oldEndTime", $this->oldEndTime->format("H:i:s"));

        $xml->addChild("newDate", $this->newDate->format("Y-m-d"));
        $xml->addChild("newStartTime", $this->newStartTime->format("H:i:s"));
        $xml->addChild("newEndTime", $this->newEndTime->format("H:i:s"));

        if ($this->newLocation !== null) {
            $xml->addChild("newLocation", $this->newLocation);
        }

        if ($this->reason !== null) {
            $xml->addChild("reason", $this->reason);
        }

        if ($this->participantIds !== null) {
            $participantsNode = $xml->addChild("participantIds");
            foreach ($this->participantIds as $id) {
                $participantsNode->addChild("participantId", $id);
            }
        }

        if ($this->icsData !== null) {
            $xml->addChild("icsData", $this->icsData);
        }

        $xml->addChild(
            "timestamp",
            $this->timestamp->format(DateTimeImmutable::ATOM), // ISO8601
        );

        return $xml->asXML();
    }

    public function getRoutingKey(): string
    {
        return "planning.session.rescheduled";
    }

    public function getType(): string
    {
        return "planning.session.rescheduled";
    }
}
