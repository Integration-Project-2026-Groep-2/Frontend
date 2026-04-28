<?php

namespace Drupal\hello_world\RabbitMQ\Message;

/**
 * Bouwt de XML-payload voor Contract 1: Frontend → CRM (nieuwe inschrijving).
 *
 * Root-element: <Registration>
 * Queue:        crm.frontend.registration.created
 * Routing key:  frontend.registration.created
 *
 * gdprConsent is xs:boolean in de master XSD → serialiseert als 'true'/'false'
 * (lowercase), niet als '1'/'0'.
 * role is RegistrationRoleType → enkel VISITOR of COMPANY_CONTACT toegelaten.
 */
class RegistrationMessage implements MessageInterface {

  private string $registrationId;
  private string $sessionId;

  public function __construct(
    private readonly string  $firstName,
    private readonly string  $lastName,
    private readonly string  $email,
    private readonly bool    $gdprConsent,
    private readonly ?string $phone   = NULL,
    private readonly ?string $company = NULL,
    private readonly string  $role    = 'VISITOR'
  ) {
    $this->registrationId = uniqid('reg_', TRUE);
    $this->sessionId      = uniqid('ses_', TRUE);
  }

  public function toXml(): string {
    $xml = new \SimpleXMLElement('<Registration/>');
    $xml->addChild('registrationId', htmlspecialchars($this->registrationId));
    $xml->addChild('firstName',      htmlspecialchars($this->firstName));
    $xml->addChild('lastName',       htmlspecialchars($this->lastName));
    $xml->addChild('email',          htmlspecialchars($this->email));
    $xml->addChild('sessionId',      htmlspecialchars($this->sessionId));
    $xml->addChild('role',           $this->role);

    // xs:boolean vereist lowercase 'true'/'false' (niet '1'/'0').
    $xml->addChild('gdprConsent', $this->gdprConsent ? 'true' : 'false');

    if (!empty($this->phone)) {
      $xml->addChild('phone', htmlspecialchars($this->phone));
    }
    if (!empty($this->company)) {
      $xml->addChild('company', htmlspecialchars($this->company));
    }

    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.registration.created';
  }

  public function getType(): string {
    return 'registration';
  }

  public function getRegistrationId(): string {
    return $this->registrationId;
  }

  public function getSessionId(): string {
    return $this->sessionId;
  }

}
