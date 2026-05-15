<?php

declare(strict_types=1);

namespace Drupal\Tests\shift_bezoeker\Unit\Form;

use Drupal\hello_world\RabbitMQ\Message\Company\CompanyCreatedMessage;
use Drupal\hello_world\RabbitMQ\Message\Registration\RegistrationMessage;
use Drupal\shift_bezoeker\Form\RegistratieForm;
use Drupal\Tests\UnitTestCase;

/**
 * Pure-mapping tests for RegistratieForm::buildMessages — verifies form
 * values land on the right XSD-shape without touching RabbitMQ.
 *
 * @coversDefaultClass \Drupal\shift_bezoeker\Form\RegistratieForm
 * @group shift_bezoeker
 */
class RegistratieFormBuildMessagesTest extends UnitTestCase {

  public function testBezoekerProducesSingleRegistrationMessage(): void {
    $messages = RegistratieForm::buildMessages([
      'registratie_type' => 'bezoeker',
      'firstName'        => 'Lars',
      'lastName'         => 'Cowe',
      'email'            => 'lars@example.test',
      'phone'            => '+32470000001',
      'role'             => 'visitor',
      'gdpr_consent'     => 1,
    ]);

    $this->assertCount(1, $messages);
    $this->assertInstanceOf(RegistrationMessage::class, $messages[0]);
    $this->assertSame('frontend.registration.created', $messages[0]->getRoutingKey());

    $xml = $messages[0]->toXml();
    $this->assertStringContainsString('<firstName>Lars</firstName>', $xml);
    $this->assertStringContainsString('<lastName>Cowe</lastName>', $xml);
    $this->assertStringContainsString('<email>lars@example.test</email>', $xml);
    $this->assertStringContainsString('<role>VISITOR</role>', $xml);
    $this->assertStringContainsString('<gdprConsent>true</gdprConsent>', $xml);
    $this->assertStringContainsString('<phone>+32470000001</phone>', $xml);
  }

  public function testBedrijfProducesRegistrationAndCompanyCreated(): void {
    $messages = RegistratieForm::buildMessages([
      'registratie_type' => 'bedrijf',
      'firstName'        => 'Lars',
      'lastName'         => 'Cowe',
      'phone'            => '+32470000001',
      'companyName'      => 'Acme NV',
      'vatNumber'        => 'be0123456789',
      'email'            => 'info@acme.test',
      'street'           => 'Stationsstraat 1',
      'city'             => 'Brussel',
      'gdpr_consent'     => 1,
    ]);

    $this->assertCount(2, $messages);
    $this->assertInstanceOf(RegistrationMessage::class, $messages[0]);
    $this->assertInstanceOf(CompanyCreatedMessage::class, $messages[1]);

    $registrationXml = $messages[0]->toXml();
    $this->assertStringContainsString('<role>COMPANY_CONTACT</role>', $registrationXml);
    $this->assertStringContainsString('<firstName>Lars</firstName>', $registrationXml);
    $this->assertStringContainsString('<lastName>Cowe</lastName>', $registrationXml);
    $this->assertStringContainsString('<phone>+32470000001</phone>', $registrationXml);
    $this->assertStringContainsString('<company>Acme NV</company>', $registrationXml);

    $companyXml = $messages[1]->toXml();
    $this->assertSame('frontend.company.created', $messages[1]->getRoutingKey());
    $this->assertStringContainsString('<name>Acme NV</name>', $companyXml);
    $this->assertStringContainsString('<vatNumber>BE0123456789</vatNumber>', $companyXml);
    $this->assertStringContainsString('<street>Stationsstraat 1</street>', $companyXml);
    $this->assertStringContainsString('<city>Brussel</city>', $companyXml);
    $this->assertStringContainsString('<country>BE</country>', $companyXml);
  }

  public function testSpeakerRoleMapsToSpreker(): void {
    $messages = RegistratieForm::buildMessages([
      'registratie_type' => 'bezoeker',
      'firstName'        => 'X',
      'lastName'         => 'Y',
      'email'            => 'x@example.test',
      'role'             => 'speaker',
      'gdpr_consent'     => 1,
    ]);
    $this->assertStringContainsString('<role>SPREKER</role>', $messages[0]->toXml());
  }

  public function testKassaRoleMapsToKassamedewerker(): void {
    $messages = RegistratieForm::buildMessages([
      'registratie_type' => 'bezoeker',
      'firstName'        => 'X',
      'lastName'         => 'Y',
      'email'            => 'x@example.test',
      'role'             => 'kassa',
      'gdpr_consent'     => 1,
    ]);
    $this->assertStringContainsString('<role>KASSAMEDEWERKER</role>', $messages[0]->toXml());
  }

  public function testEmptyRoleDefaultsToVisitor(): void {
    $messages = RegistratieForm::buildMessages([
      'registratie_type' => 'bezoeker',
      'firstName'        => 'X',
      'lastName'         => 'Y',
      'email'            => 'x@example.test',
      'gdpr_consent'     => 1,
    ]);
    $this->assertStringContainsString('<role>VISITOR</role>', $messages[0]->toXml());
  }

  public function testBedrijfVatLowercaseGetsUppercasedInMessage(): void {
    $messages = RegistratieForm::buildMessages([
      'registratie_type' => 'bedrijf',
      'firstName'        => 'Lars',
      'lastName'         => 'Cowe',
      'companyName'      => 'Acme NV',
      'vatNumber'        => 'be0123456789',
      'gdpr_consent'     => 1,
      'email'            => 'info@acme.test',
    ]);
    $companyXml = $messages[1]->toXml();
    $this->assertStringContainsString('<vatNumber>BE0123456789</vatNumber>', $companyXml);
  }

  public function testBedrijfWithMissingOptionalAddressFieldsOmitsThem(): void {
    $messages = RegistratieForm::buildMessages([
      'registratie_type' => 'bedrijf',
      'firstName'        => 'Lars',
      'lastName'         => 'Cowe',
      'companyName'      => 'Acme NV',
      'vatNumber'        => 'BE0123456789',
      'email'            => 'info@acme.test',
      'gdpr_consent'     => 1,
    ]);
    $companyXml = $messages[1]->toXml();
    $this->assertStringNotContainsString('<street', $companyXml);
    $this->assertStringNotContainsString('<city', $companyXml);
    $this->assertStringContainsString('<country>BE</country>', $companyXml);
  }

  public function testBedrijfRegistrationCarriesContactPersonNameNotCompanyName(): void {
    /* Regression-guard for prod incident 2026-05-09: SF Contact got created
     * with FirstName="Fresh BV", LastName="-" because the form had no
     * persoon-fields for bedrijf. Now the contact-persoon name flows correctly. */
    $messages = RegistratieForm::buildMessages([
      'registratie_type' => 'bedrijf',
      'firstName'        => 'Lars',
      'lastName'         => 'Cowe',
      'companyName'      => 'Acme NV',
      'vatNumber'        => 'BE0123456789',
      'email'            => 'lars@acme.test',
      'gdpr_consent'     => 1,
    ]);
    $registrationXml = $messages[0]->toXml();
    $this->assertStringContainsString('<firstName>Lars</firstName>', $registrationXml);
    $this->assertStringContainsString('<lastName>Cowe</lastName>', $registrationXml);
    $this->assertStringNotContainsString('<firstName>Acme NV</firstName>', $registrationXml);
    $this->assertStringNotContainsString('<lastName>-</lastName>', $registrationXml);
  }

}
