<?php

namespace Drupal\hello_world\RabbitMQ\Message;

/**
 * Builds the XML payload for a heartbeat ping.
 */
class HeartbeatMessage implements MessageInterface {

  private string $serviceId;
  private string $timestamp;

  public function __construct(string $serviceId = 'frontend') {
    $this->serviceId = $serviceId;
    $this->timestamp = date('c');
  }

  public function toXml(): string {
    $xml = new \SimpleXMLElement('<Heartbeat/>');
    $xml->addChild('serviceId',  htmlspecialchars($this->serviceId));
    $xml->addChild('timestamp',  $this->timestamp);
    return $xml->asXML();
  }

  public function getRoutingKey(): string {
    return 'routing.heartbeat';
  }

  public function getType(): string {
    return 'heartbeat';
  }

}
