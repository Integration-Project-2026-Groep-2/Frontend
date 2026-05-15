<?php

namespace Drupal\hello_world\RabbitMQ\Message\Planning;

use SimpleXMLElement;

/**
 * Contract: planning.registration.created
 * Routing:  frontend.registration.created
 * Element:  RegistrationCreated
 */
final class RegistrationCreatedMessage extends Planning {

  public function __construct(
    private readonly string $registrationId,
    private readonly string $sessionId,
    private readonly string $userId,
    private readonly bool   $isActive,
    private readonly ?string $timestamp = NULL,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<RegistrationCreated/>');
    $xml->addChild('registrationId', $this->registrationId);
    $xml->addChild('sessionId',      $this->sessionId);
    $xml->addChild('userId',         $this->userId);
    $xml->addChild('isActive',       $this->isActive ? 'true' : 'false');
    
    if ($this->timestamp) {
      $xml->addChild('timestamp', $this->timestamp);
    }

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.registration.created';
  }

  public function getType(): string {
    return 'registration_created';
  }

}
