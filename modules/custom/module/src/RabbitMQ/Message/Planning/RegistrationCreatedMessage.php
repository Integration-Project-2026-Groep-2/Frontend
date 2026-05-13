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
    private readonly string $sessionId,
    private readonly string $crmMasterId,
  ) {}

  public function toXml(): string {
    $xml = new SimpleXMLElement('<RegistrationCreated/>');
    $xml->addChild('sessionId',   $this->sessionId);
    $xml->addChild('crmMasterId', $this->crmMasterId);

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.registration.created';
  }

  public function getType(): string {
    return 'registration_created';
  }

}
