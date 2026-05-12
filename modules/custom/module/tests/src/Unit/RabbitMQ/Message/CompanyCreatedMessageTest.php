<?php

declare(strict_types=1);

namespace Drupal\Tests\hello_world\Unit\RabbitMQ\Message;

use Drupal\hello_world\RabbitMQ\Message\Company\CompanyCreatedMessage;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\hello_world\RabbitMQ\Message\Company\CompanyCreatedMessage
 * @group hello_world
 */
class CompanyCreatedMessageTest extends UnitTestCase {

  private function frontendContractXsd(): ?string {
    $candidates = [
      !empty($_ENV['XSD_ROOT']) ? $_ENV['XSD_ROOT'] . '/frontend-contract.xsd' : NULL,
      '/opt/drupal/xsd/frontend-contract.xsd',
      dirname(__DIR__, 8) . '/xsd/frontend-contract.xsd',
      dirname(__DIR__, 9) . '/xsd/frontend-contract.xsd',
    ];
    foreach ($candidates as $c) {
      if ($c !== NULL && file_exists($c)) {
        return $c;
      }
    }
    return NULL;
  }

  public function testToXmlBuildsValidStructure(): void {
    $msg = new CompanyCreatedMessage('Acme NV', 'BE0123456789');
    $xml = $msg->toXml();

    $dom = new \DOMDocument();
    $this->assertTrue($dom->loadXML($xml));
    $this->assertSame('CompanyCreated', $dom->documentElement->localName);
    $this->assertSame('urn:frontend:crm:contract', $dom->documentElement->namespaceURI);

    $children = [];
    foreach ($dom->documentElement->childNodes as $node) {
      if ($node->nodeType === XML_ELEMENT_NODE) {
        $children[$node->localName] = $node->textContent;
      }
    }
    $this->assertSame('Acme NV', $children['name'] ?? NULL);
    $this->assertSame('BE0123456789', $children['vatNumber'] ?? NULL);
  }

  public function testToXmlValidatesAgainstFrontendContractXsd(): void {
    $xsd = $this->frontendContractXsd();
    if ($xsd === NULL) {
      $this->markTestSkipped('frontend-contract.xsd not reachable — set XSD_ROOT env-var or run from container.');
    }

    $msg = new CompanyCreatedMessage(
      'Acme NV',
      'BE0123456789',
      'info@acme.test',
      '+3221234567',
      'Stationsstraat',
      '1',
      '1000',
      'Brussel',
      'BE',
    );

    $dom = new \DOMDocument();
    $dom->loadXML($msg->toXml());

    libxml_use_internal_errors(TRUE);
    $valid  = $dom->schemaValidate($xsd);
    $errors = array_map(
      static fn(\LibXMLError $e) => trim($e->message) . ' (line ' . $e->line . ')',
      libxml_get_errors(),
    );
    libxml_clear_errors();
    libxml_use_internal_errors(FALSE);

    $this->assertTrue($valid, 'XSD validation failed: ' . implode('; ', $errors));
  }

  public function testRoutingKeyAndType(): void {
    $msg = new CompanyCreatedMessage('Acme NV', 'BE0123456789');
    $this->assertSame('frontend.company.created', $msg->getRoutingKey());
    $this->assertSame('company_created', $msg->getType());
  }

  public function testOptionalFieldsOmittedWhenEmpty(): void {
    $msg = new CompanyCreatedMessage('Acme NV', 'BE0123456789');
    $xml = $msg->toXml();

    $this->assertStringNotContainsString('<email', $xml);
    $this->assertStringNotContainsString('<phone', $xml);
    $this->assertStringNotContainsString('<street', $xml);
    $this->assertStringNotContainsString('<houseNumber', $xml);
    $this->assertStringNotContainsString('<postalCode', $xml);
    $this->assertStringNotContainsString('<city', $xml);
    $this->assertStringNotContainsString('<country', $xml);
  }

  public function testEmptyStringOptionalsAreOmitted(): void {
    $msg = new CompanyCreatedMessage('Acme NV', 'BE0123456789', '', '');
    $xml = $msg->toXml();
    $this->assertStringNotContainsString('<email', $xml);
    $this->assertStringNotContainsString('<phone', $xml);
  }

  public function testConstructorRejectsEmptyName(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('CompanyCreated.name');
    new CompanyCreatedMessage('   ', 'BE0123456789');
  }

  public function testConstructorRejectsInvalidVat(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('BTW-nummer');
    new CompanyCreatedMessage('Acme NV', 'NL0123456789');
  }

  public function testConstructorAcceptsVatWithoutBePrefix(): void {
    $msg = new CompanyCreatedMessage('Acme NV', '0123456789');
    $this->assertStringContainsString('0123456789', $msg->toXml());
  }

  public function testConstructorRejectsInvalidCountry(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('country-code');
    new CompanyCreatedMessage(
      'Acme NV', 'BE0123456789',
      NULL, NULL, NULL, NULL, NULL, NULL, 'be',
    );
  }

  public function testSpecialCharactersInNameAreEscaped(): void {
    $msg = new CompanyCreatedMessage('Acme & Co <NV>', 'BE0123456789');
    $xml = $msg->toXml();

    $dom = new \DOMDocument();
    $this->assertTrue($dom->loadXML($xml));
    $name = $dom->getElementsByTagNameNS('urn:frontend:crm:contract', 'name')->item(0);
    $this->assertSame('Acme & Co <NV>', $name->textContent);
  }

}
