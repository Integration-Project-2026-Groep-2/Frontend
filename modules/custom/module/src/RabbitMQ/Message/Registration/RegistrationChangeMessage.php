<?php

namespace Drupal\hello_world\RabbitMQ\Message;

/**
 * Bouwt de XML-payload voor Contract 2: Frontend → CRM (inschrijving wijzigen).
 *
 * Root-element: <RegistrationChange>
 * Routing key:  frontend.user.updated
 */
class RegistrationChangeMessage implements MessageInterface {

  public function __construct(
    private readonly string  $email,
    private readonly string  $sessionId,
    private readonly string  $changeType = 'updated',
    private readonly ?string $firstName  = NULL,
    private readonly ?string $lastName   = NULL,
    private readonly ?string $phone      = NULL,
    private readonly ?string $role       = NULL,
    private readonly ?string $company    = NULL,
    private readonly ?string $registrationId = NULL,
  ) {}

  public function toXml(): string {
    $ns  = 'urn:frontend:crm:contract';
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = TRUE;

    $root = $dom->createElementNS($ns, 'RegistrationChange');
    $dom->appendChild($root);

    if (!empty($this->registrationId)) {
      $root->appendChild($dom->createElementNS($ns, 'registrationId', htmlspecialchars($this->registrationId)));
    }

    $root->appendChild($dom->createElementNS($ns, 'email',      htmlspecialchars($this->email)));
    $root->appendChild($dom->createElementNS($ns, 'changeType', $this->changeType));

    // updatedFields — alleen toevoegen als er iets gewijzigd is.
    $hasUpdatedFields = $this->firstName || $this->lastName || $this->phone || $this->role || $this->company;
    if ($hasUpdatedFields) {
      $updatedFields = $dom->createElementNS($ns, 'updatedFields');
      $root->appendChild($updatedFields);

      foreach ([
        'firstName' => $this->firstName,
        'lastName'  => $this->lastName,
        'phone'     => $this->phone,
        'role'      => $this->role,
        'company'   => $this->company,
      ] as $name => $value) {
        if (!empty($value)) {
          $updatedFields->appendChild($dom->createElementNS($ns, $name, htmlspecialchars($value)));
        }
      }
    }

    return $dom->saveXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.user.updated';
  }

  public function getType(): string {
    return 'registration_change';
  }

}
