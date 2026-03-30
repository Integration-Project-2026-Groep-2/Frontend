<?php

namespace Drupal\rabbitmq_integration\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Consumes messages from RabbitMQ queues into Drupal.
 *
 * Designed for two patterns:
 *  1. Long-running consumer (Drush command / daemon) using startConsuming().
 *  2. Single-message fetch using getOneMessage() (for RPC replies, cron jobs).
 */
class RabbitMQConsumer {

  /**
   * Registered message handler callbacks, keyed by queue name.
   *
   * @var callable[]
   */
  protected array $handlers = [];

  public function __construct(
    protected RabbitMQConnectionManager $connectionManager,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Registers a handler callable for a specific queue.
   *
   * The callable receives an AMQPMessage. It must call
   * $message->ack() or $message->nack() before returning.
   *
   * @param string   $queueName
   * @param callable $handler  function(AMQPMessage $msg): void
   */
  public function registerHandler(string $queueName, callable $handler): void {
    $this->handlers[$queueName] = $handler;
  }

  /**
   * Starts consuming all enabled queues (blocking loop).
   *
   * Meant to be called from a Drush command or daemon process.
   *
   * @param int $timeLimit  Stop after this many seconds (0 = run forever).
   */
  public function startConsuming(int $timeLimit = 0): void {
    $config      = $this->configFactory->get('rabbitmq_integration.settings');
    $queues      = $config->get('consume_queues') ?? [];
    $channel     = $this->connectionManager->getChannel();
    $exchange    = $config->get('exchange_name') ?? 'drupal_exchange';
    $start       = time();

    $this->connectionManager->declareExchange($channel);

    foreach ($queues as $queueKey => $queueConfig) {
      if (!($queueConfig['enabled'] ?? false)) {
        continue;
      }

      $queueName   = $queueConfig['queue_name'];
      $routingKey  = $queueConfig['routing_key'];
      $prefetch    = (int) ($queueConfig['prefetch_count'] ?? 10);

      // Declare the queue (idempotent).
      $channel->queue_declare($queueName, false, true, false, false);

      // Bind queue to exchange with routing key.
      $channel->queue_bind($queueName, $exchange, $routingKey);

      // QoS: how many messages to prefetch at once.
      $channel->basic_qos(null, $prefetch, null);

      // Determine the handler to use.
      $handler = $this->handlers[$queueName]
        ?? $this->handlers[$queueKey]
        ?? [$this, 'defaultHandler'];

      $consumerTag = 'drupal_consumer_' . $queueKey . '_' . getmypid();

      $channel->basic_consume(
        queue:       $queueName,
        consumer_tag: $consumerTag,
        no_local:    false,
        no_ack:      false,    // We manually ack.
        exclusive:   false,
        nowait:      false,
        callback:    $handler,
      );

      $this->loggerFactory->get('rabbitmq_integration')->info(
        'Consuming queue "@queue" (key: @key) with tag "@tag".',
        ['@queue' => $queueName, '@key' => $routingKey, '@tag' => $consumerTag]
      );
    }

    // Block and wait for messages.
    while ($channel->is_consuming()) {
      $channel->wait(null, false, 1.0);

      if ($timeLimit > 0 && (time() - $start) >= $timeLimit) {
        $this->loggerFactory->get('rabbitmq_integration')
          ->info('Consumer time limit of @s seconds reached. Stopping.', ['@s' => $timeLimit]);
        break;
      }
    }
  }

  /**
   * Fetches a single message from a named queue (non-blocking).
   *
   * Returns null if the queue is empty.
   *
   * @param string $queueName
   * @param bool   $autoAck  If TRUE, message is acked automatically.
   */
  public function getOneMessage(string $queueName, bool $autoAck = false): ?AMQPMessage {
    try {
      $channel = $this->connectionManager->getChannel();
      $channel->queue_declare($queueName, false, true, false, false);
      return $channel->basic_get($queueName, $autoAck) ?: null;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rabbitmq_integration')->error(
        'Error fetching message from queue "@q": @msg',
        ['@q' => $queueName, '@msg' => $e->getMessage()]
      );
      return null;
    }
  }

  /**
   * Waits for an RPC reply on a temporary queue within a time limit.
   *
   * @param string $replyQueue    The reply_to queue name.
   * @param string $correlationId The correlation ID to match.
   * @param float  $timeout       Seconds to wait before giving up.
   *
   * @return array|null  Decoded JSON payload or NULL on timeout.
   */
  public function waitForRpcReply(string $replyQueue, string $correlationId, float $timeout = 5.0): ?array {
    $result  = null;
    $channel = $this->connectionManager->getChannel();

    // Declare an exclusive, auto-delete reply queue.
    $channel->queue_declare($replyQueue, false, false, true, true);

    $channel->basic_consume(
      queue:    $replyQueue,
      no_ack:   true,
      callback: function (AMQPMessage $msg) use ($correlationId, &$result, $channel) {
        if ($msg->get('correlation_id') === $correlationId) {
          $result = json_decode($msg->getBody(), true);
          // Stop consuming after we have our reply.
          $channel->basic_cancel($msg->getConsumerTag());
        }
      }
    );

    $deadline = microtime(true) + $timeout;
    while ($result === null && microtime(true) < $deadline) {
      $channel->wait(null, false, $timeout);
    }

    return $result;
  }

  /**
   * Default fallback handler — logs and acknowledges.
   */
  public function defaultHandler(AMQPMessage $message): void {
    $body    = $message->getBody();
    $decoded = json_decode($body, true);
    $event   = $decoded['event'] ?? 'unknown';

    $this->loggerFactory->get('rabbitmq_integration')->info(
      'Received message on queue "@queue" with event "@event" — no custom handler registered.',
      [
        '@queue' => $message->getRoutingKey() ?? 'unknown',
        '@event' => $event,
      ]
    );

    // Acknowledge so it's removed from the queue.
    $message->ack();
  }

}
