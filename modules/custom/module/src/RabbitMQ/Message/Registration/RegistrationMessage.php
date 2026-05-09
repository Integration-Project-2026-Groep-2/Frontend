<?php

namespace Drupal\hello_world\RabbitMQ\Message;

/**
 * Bouwt de XML-payload voor Contract 1: Frontend → CRM (nieuwe inschrijving).
 *
 * Gebaseerd op de frontend-contract XSD (urn:frontend:crm:contract).
 *
 * Verschillen t.o.v. de master XSD:
 *  - role waarden zijn lowercase (visitor, company_contact, ...)
 *  - sessionId wordt NIET meer meegestuurd
 *  - XML heeft een targetNamespace: urn:frontend:crm:contract
 */
class RegistrationMessage implements MessageInterface {

  private string $registrationId;

  // Geldige rollen volgens de frontend-contract XSD.
  public const ROLES = [
    'visitor',
    'company_contact',
    'spreker',
    'kassamedewerker',
    'sysadmin',
    'eventbeheerder',
  ];

  public function __construct(
    private readonly string  $firstName,
    private readonly string  $lastName,
    private readonly string  $email,
    private readonly bool    $gdprConsent,
    private readonly ?string $phone   = NULL,
    private readonly ?string $company = NULL,
    private readonly string  $role    = 'visitor'
  ) {
    if (!in_array($this->role, self::ROLES, TRUE)) {
      throw new \InvalidArgumentException(
        sprintf('Ongeldige rol "%s". Geldige rollen: %s', $role, implode(', ', self::ROLES))
      );
    }
    $this->registrationId = uniqid('reg_', TRUE);
  }

  public function toXml(): string {
    $ns = 'urn:frontend:crm:contract';

    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = TRUE;

    $root = $dom->createElementNS($ns, 'Registration');
    $dom->appendChild($root);

    $fields = [
      'registrationId' => $this->registrationId,
      'firstName'      => $this->firstName,
      'lastName'       => $this->lastName,
      'email'          => $this->email,
      'role'           => $this->role,
      'gdprConsent'    => $this->gdprConsent ? 'true' : 'false',
    ];

    foreach ($fields as $name => $value) {
      $el = $dom->createElementNS($ns, $name, htmlspecialchars((string) $value));
      $root->appendChild($el);
    }

    if (!empty($this->phone)) {
      $el = $dom->createElementNS($ns, 'phone', htmlspecialchars($this->phone));
      $root->appendChild($el);
    }
    if (!empty($this->company)) {
      $el = $dom->createElementNS($ns, 'company', htmlspecialchars($this->company));
      $root->appendChild($el);
    }

    return $dom->saveXML();
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

}
