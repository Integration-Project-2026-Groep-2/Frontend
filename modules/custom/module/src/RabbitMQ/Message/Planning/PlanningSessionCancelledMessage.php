<?php

namespace Drupal\hello_world\RabbitMQ\Message;

use DateTimeImmutable;
use Planning;
use SimpleXMLElement;

final class PlanningCancelledMessage extends Planning implements
    MessageInterface
{
    private string $sessionName;
    private string $status;

    private ?string $reason;

    /** @var string[]|null */
    private ?array $participantIds;

    private ?string $icsData;

    public function __construct(
        string $sessionId,
        string $sessionName,
        PlanningSessionStatusType $status,
        ?string $reason,
        ?array $participantIds,
        ?string $icsData,
        \DateTimeImmutable $timestamp,
    ) {
        parent::_construct($sessionId, $timestamp);
        $this->sessionName = $sessionName;
        $this->status = $status;

        $this->reason = $reason;
        $this->participantIds = $participantIds;
        $this->icsData = $icsData;
    }

    public function toXml(): string
    {
        $xml = new SimpleXMLElement("<SessionCancelled/>");

        $xml->addChild("sessionId", $this->sessionId);
        $xml->addChild("sessionName", $this->sessionName);
        $xml->addChild("status", $this->status);

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
            $this->timestamp->format(DateTimeImmutable::ATOM),
        );

        return $xml->asXML();
    }

    public function getRoutingKey(): string
    {
        return "planning.session.cancelled";
    }

    public function getType(): string
    {
        return "planning.session.cancelled";
    }
}
