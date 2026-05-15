<?php

declare(strict_types=1);

namespace Drupal\Tests\Session_Management\Unit\RabbitMQ\Message;

use Drupal\Session_Management\RabbitMQ\Message\SessionListRequest;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Session_Management\RabbitMQ\Message\SessionListRequest
 * @group session_management
 */
class SessionListRequestTest extends UnitTestCase {

  public function testRoutingKeyIsCorrect(): void {
    $msg = new SessionListRequest();
    $this->assertSame('session.list.request', $msg->getRoutingKey());
  }

  public function testGetTypeIsCorrect(): void {
    $msg = new SessionListRequest();
    $this->assertSame('SessionListRequest', $msg->getType());
  }

  public function testToXmlProducesValidXml(): void {
    $msg = new SessionListRequest();
    $xml = $msg->toXml();

    $dom = new \DOMDocument();
    $this->assertTrue($dom->loadXML($xml), 'toXml() moet geldige XML produceren');
    $this->assertSame('SessionListRequest', $dom->documentElement->localName);
  }

  public function testToXmlBevatRequestId(): void {
    $msg = new SessionListRequest();
    $dom = new \DOMDocument();
    $dom->loadXML($msg->toXml());

    $requestId = $dom->getElementsByTagName('requestId')->item(0);
    $this->assertNotNull($requestId, '<requestId> element moet aanwezig zijn');
    $this->assertStringStartsWith('req_', $requestId->textContent);
  }

  public function testToXmlBevatTimestamp(): void {
    $msg = new SessionListRequest();
    $dom = new \DOMDocument();
    $dom->loadXML($msg->toXml());

    $timestamp = $dom->getElementsByTagName('timestamp')->item(0);
    $this->assertNotNull($timestamp, '<timestamp> element moet aanwezig zijn');
    $this->assertNotEmpty($timestamp->textContent);
  }

  public function testRequestIdIsUniekPerAanroep(): void {
    $msg = new SessionListRequest();
    $dom1 = new \DOMDocument();
    $dom1->loadXML($msg->toXml());
    $id1 = $dom1->getElementsByTagName('requestId')->item(0)->textContent;

    $dom2 = new \DOMDocument();
    $dom2->loadXML($msg->toXml());
    $id2 = $dom2->getElementsByTagName('requestId')->item(0)->textContent;

    $this->assertNotSame($id1, $id2, 'Elk verzoek moet een unieke requestId hebben');
  }

}
