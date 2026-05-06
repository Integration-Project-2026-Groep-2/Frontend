<?php

namespace Drupal\hello_world\RabbitMQ\Message;

/**
 * Contract for all RabbitMQ message types.
 */
interface MessageInterface {

  /**
   * Build and return the XML payload as a string.
   */
  public function toXml(): string;

  /**
   * Return the routing key for this message.
   * e.g. 'frontend.registration.created'
   */
  public function getRoutingKey(): string;

  /**
   * Return the message type identifier used for XSD lookup.
   * e.g. 'registration', 'heartbeat', 'user_update'
   */
  public function getType(): string;

}
