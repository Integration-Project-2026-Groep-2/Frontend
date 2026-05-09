<?php

namespace Drupal\hello_world\RabbitMQ\Message\Company;

/**
 * Bouwt de XML-payload voor Contract 3: Frontend → CRM (nieuw bedrijf).
 *
 * Volgt de frontend-contract XSD (urn:frontend:crm:contract):
 *  - name + vatNumber zijn verplicht
 *  - alle adres- + contactvelden optioneel (minOccurs=0)
 *  - vatNumber-pattern: (BE\s?)?(0\d{9})
 *  - country-pattern:   [A-Z]{2}
 */
class CompanyCreatedMessage implements \Drupal\hello_world\RabbitMQ\Message\MessageInterface {

  public function __construct(
    private readonly string  $name,
    private readonly string  $vatNumber,
    private readonly ?string $email       = NULL,
    private readonly ?string $phone       = NULL,
    private readonly ?string $street      = NULL,
    private readonly ?string $houseNumber = NULL,
    private readonly ?string $postalCode  = NULL,
    private readonly ?string $city        = NULL,
    private readonly ?string $country     = NULL,
  ) {
    if (trim($this->name) === '') {
      throw new \InvalidArgumentException('CompanyCreated.name mag niet leeg zijn.');
    }
    if (!preg_match('/^(BE\s?)?(0\d{9})$/', $this->vatNumber)) {
      throw new \InvalidArgumentException(
        sprintf('Ongeldig BTW-nummer "%s". Verwacht patroon: (BE )?0XXXXXXXXX.', $this->vatNumber)
      );
    }
    if ($this->country !== NULL && !preg_match('/^[A-Z]{2}$/', $this->country)) {
      throw new \InvalidArgumentException(
        sprintf('Ongeldige country-code "%s". Verwacht: 2 hoofdletters (bv. BE).', $this->country)
      );
    }
  }

  public function toXml(): string {
    $ns = 'urn:frontend:crm:contract';

    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = TRUE;

    $root = $dom->createElementNS($ns, 'CompanyCreated');
    $dom->appendChild($root);

    $required = [
      'name'      => $this->name,
      'vatNumber' => $this->vatNumber,
    ];
    foreach ($required as $tag => $value) {
      $root->appendChild($dom->createElementNS($ns, $tag, htmlspecialchars((string) $value)));
    }

    $optional = [
      'email'       => $this->email,
      'phone'       => $this->phone,
      'street'      => $this->street,
      'houseNumber' => $this->houseNumber,
      'postalCode'  => $this->postalCode,
      'city'        => $this->city,
      'country'     => $this->country,
    ];
    foreach ($optional as $tag => $value) {
      if ($value === NULL || $value === '') {
        continue;
      }
      $root->appendChild($dom->createElementNS($ns, $tag, htmlspecialchars((string) $value)));
    }

    return $dom->saveXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.company.created';
  }

  public function getType(): string {
    return 'company_created';
  }

}
