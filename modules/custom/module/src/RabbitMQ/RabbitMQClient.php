<?php

namespace Drupal\hello_world\RabbitMQ;

use Drupal\hello_world\RabbitMQ\Message\MessageInterface;
use Drupal\hello_world\RabbitMQ\Validation\XsdRegistry;
use Drupal\hello_world\RabbitMQ\Validation\XsdValidator;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Central RabbitMQ client for publishing and consuming messages.
 *
 * Handles:
 *  - Connection with configurable retry logic
 *  - XSD validation before publish
 *  - Exchange declaration
 *  - Clean teardown
 */
class RabbitMQClient {

  private ?AMQPStreamConnection $connection = NULL;
  private mixed $channel = NULL;
  private XsdValidator $validator;

  // Exchange names used by this module.
  private const EXCHANGE_TOPIC     = 'user.topic';
  private const EXCHANGE_HEARTBEAT = 'heartbeat.direct';

  public function __construct(
    private readonly string $host,
    private readonly int    $port,
    private readonly string $user,
    private readonly string $pass,
    private readonly int    $maxRetries = 10,
    private readonly int    $retryDelay = 5,   // seconds
    ?XsdValidator $validator = NULL
  ) {
    $this->validator = $validator ?? new XsdValidator(new XsdRegistry());
  }

  // ---------------------------------------------------------------------------
  // Factory — builds from $_ENV automatically
  // ---------------------------------------------------------------------------

  public static function fromEnv(): self {
    return new self(
      $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
      (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
      $_ENV['RABBITMQ_USER'] ?? 'guest',
      $_ENV['RABBITMQ_PASS'] ?? 'guest',
    );
  }

  // ---------------------------------------------------------------------------
  // Connection management
  // ---------------------------------------------------------------------------

  /**
   * Opens a connection (with retry) and declares all exchanges.
   *
   * Safe to call multiple times — skips if already connected.
   *
   * @throws \RuntimeException When the broker is unreachable after all retries.
   */
  public function connect(): void {
    if ($this->connection && $this->connection->isConnected()) {
      return;
    }

    $attempt = 0;
    while ($attempt < $this->maxRetries) {
      try {
        $this->connection = new AMQPStreamConnection(
          $this->host, $this->port, $this->user, $this->pass
        );
        $this->channel = $this->connection->channel();
        $this->declareExchanges();
        return;
      }
      catch (\Exception $e) {
        $attempt++;
        if ($attempt >= $this->maxRetries) {
          throw new \RuntimeException(
            sprintf('RabbitMQ unreachable after %d retries: %s', $this->maxRetries, $e->getMessage()),
            0,
            $e
          );
        }
        echo sprintf("RabbitMQ not ready (attempt %d/%d), retrying in %ds…\n",
          $attempt, $this->maxRetries, $this->retryDelay);
        sleep($this->retryDelay);
      }
    }
  }

  /**
   * Closes channel and connection gracefully.
   */
  public function disconnect(): void {
    try {
      $this->channel?->close();
      $this->connection?->close();
    }
    catch (\Exception) {
      // Ignore teardown errors.
    }
    finally {
      $this->channel    = NULL;
      $this->connection = NULL;
    }
  }

  // ---------------------------------------------------------------------------
  // Publishing
  // ---------------------------------------------------------------------------

  /**
   * Validates the message against its XSD, then publishes to the correct
   * exchange using the message's own routing key.
   *
   * @throws \RuntimeException On validation failure or publish error.
   */
  public function publish(MessageInterface $message): void {
    $this->connect();

    $xml = $message->toXml();

    // Validate before we touch the broker.
    $this->validator->validate($xml, $message->getType());

    $exchange = $this->resolveExchange($message->getRoutingKey());

    $amqpMessage = new AMQPMessage(
      $xml,
      ['content_type' => 'text/xml', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
    );

    $this->channel->basic_publish($amqpMessage, $exchange, $message->getRoutingKey());
  }

  // ---------------------------------------------------------------------------
  // Consuming
  // ---------------------------------------------------------------------------

  /**
   * Starts a blocking consumer on $queueName.
   *
   * $callback receives an AMQPMessage; call $msg->ack() when done.
   *
   * @param string   $queueName  Queue to consume from.
   * @param callable $callback   fn(AMQPMessage $msg): void
   */
  public function consume(string $queueName, callable $callback): void {
    $this->connect();

    $this->channel->queue_declare($queueName, FALSE, TRUE, FALSE, FALSE);
    $this->channel->basic_qos(NULL, 1, NULL);   // fair dispatch
    $this->channel->basic_consume(
      $queueName,
      '',     // consumer tag
      FALSE,  // no local
      FALSE,  // no ack (manual ack required)
      FALSE,  // exclusive
      FALSE,  // no wait
      $callback
    );

    while ($this->channel->is_consuming()) {
      $this->channel->wait();
    }
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  private function declareExchanges(): void {
    // topic exchange for registration & user events
    $this->channel->exchange_declare(
      self::EXCHANGE_TOPIC, 'topic', FALSE, TRUE, FALSE
    );
    // direct exchange for heartbeat
    $this->channel->exchange_declare(
      self::EXCHANGE_HEARTBEAT, 'direct', FALSE, TRUE, FALSE
    );
  }

  /**
   * Determines which exchange to use from the routing key prefix.
   */
  private function resolveExchange(string $routingKey): string {
    if (str_starts_with($routingKey, 'routing.heartbeat')) {
      return self::EXCHANGE_HEARTBEAT;
    }
    return self::EXCHANGE_TOPIC;
  }

}
