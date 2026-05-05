<?php

namespace Drupal\Session_Management\RabbitMQ\Message;

use Drupal\hello_world\RabbitMQ\Message\MessageInterface;

class SessionListRequest implements MessageInterface {

  public function toXml(): string {
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SessionListRequest>
  <requestId>{$this->generateId()}</requestId>
  <timestamp>{$this->getTimestamp()}</timestamp>
</SessionListRequest>
XML;
  }

  public function getRoutingKey(): string {
    return 'session.list.request';
  }

  public function getType(): string {   
    return 'SessionListRequest';
  }

  private function generateId(): string {
    return uniqid('req_');
  }

  private function getTimestamp(): string {
    return date('c');
  }

}