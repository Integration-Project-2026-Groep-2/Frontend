<?php

namespace Drupal\rabbitmq_integration\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Publishes messages from Drupal to RabbitMQ exchanges.
 *
 * Usage example:
 * @code
 *   $publisher = \Drupal::service('rabbitmq_integration.publisher');
 *   $publisher->publish('user.registered', ['uid' => 42, 'email' => 'foo@bar.com']);
 * @endcode
 */
class RabbitMQPublisher {

  public function __construct(
    protected RabbitMQConnectionManager $connectionManager,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Publishes a message to the configured exchange.
   *
   * @param string $routingKey
   *   The routing key (e.g. 'user.registered', 'event.update.42').
   * @param array $payload
   *   The message data. Will be JSON-encoded.
   * @param array $options
   *   Optional overrides:
   *   - 'exchange'     : override the exchange name.
   *   - 'content_type' : default 'application/json'.
   *   - 'persistent'   : bool, default TRUE (delivery_mode = 2).
   *   - 'correlation_id': string, for RPC patterns.
   *   - 'reply_to'     : queue name for RPC replies.
   *
   * @return bool  TRUE on success, FALSE on failure.
   */
  public function publish(string $routingKey, array $payload, array $options = []): bool {
    $config = $this->configFactory->get('rabbitmq_integration.settings');

    // Check if this routing key is enabled.
    $publishQueues = $config->get('publish_queues') ?? [];
    foreach ($publishQueues as $queueConfig) {
      if (($queueConfig['routing_key'] ?? '') === $routingKey
          && !($queueConfig['enabled'] ?? true)) {
        $this->loggerFactory->get('rabbitmq_integration')->info(
          'Publishing to @key is disabled by config — message dropped.',
          ['@key' => $routingKey]
        );
        return false;
      }
    }

    try {
      $channel  = $this->connectionManager->getChannel();
      $exchange = $options['exchange'] ?? $config->get('exchange_name') ?? 'drupal_exchange';

      $this->connectionManager->declareExchange($channel);

      // Build message properties.
      $properties = [
        'content_type'  => $options['content_type'] ?? 'application/json',
        'delivery_mode' => ($options['persistent'] ?? true) ? AMQPMessage::DELIVERY_MODE_PERSISTENT : AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
        'timestamp'     => time(),
        'app_id'        => 'drupal-rabbitmq-integration',
        'message_id'    => $this->generateMessageId(),
      ];

      if (!empty($options['correlation_id'])) {
        $properties['correlation_id'] = $options['correlation_id'];
      }
      if (!empty($options['reply_to'])) {
        $properties['reply_to'] = $options['reply_to'];
      }

      // Add custom headers with Drupal metadata.
      $properties['application_headers'] = new AMQPTable([
        'X-Drupal-Site' => \Drupal::request()->getHost(),
        'X-Source'      => 'drupal',
        'X-Routing-Key' => $routingKey,
      ]);

      $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $message = new AMQPMessage($body, $properties);

      $channel->basic_publish($message, $exchange, $routingKey);

      $this->loggerFactory->get('rabbitmq_integration')->info(
        'Published message to exchange "@exchange" with routing key "@key". Message ID: @id',
        [
          '@exchange' => $exchange,
          '@key'      => $routingKey,
          '@id'       => $properties['message_id'],
        ]
      );

      return true;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rabbitmq_integration')->error(
        'Failed to publish message with routing key "@key": @msg',
        ['@key' => $routingKey, '@msg' => $e->getMessage()]
      );

      // Reset connection so next attempt gets a fresh one.
      $this->connectionManager->closeConnection();
      return false;
    }
  }

  /**
   * Publishes a user registration event.
   *
   * Convenience wrapper for the user.registered routing key.
   */
  public function publishUserRegistration(array $userData): bool {
    $payload = [
      'event'      => 'user.registered',
      'timestamp'  => (new \DateTime())->format(\DateTime::ATOM),
      'source'     => 'drupal',
      'user'       => [
        'uid'          => $userData['uid'] ?? null,
        'name'         => $userData['name'] ?? null,
        'email'        => $userData['email'] ?? null,
        'created'      => $userData['created'] ?? null,
        'roles'        => $userData['roles'] ?? [],
        'language'     => $userData['language'] ?? 'en',
        'extra_fields' => $userData['extra_fields'] ?? [],
      ],
    ];

    return $this->publish('user.registered', $payload);
  }

  /**
   * Sends a request to another app asking for event companies.
   *
   * Uses RPC pattern: publishes a request and provides a reply_to queue.
   *
   * @param int    $eventId    The event ID to query.
   * @param string $replyQueue The queue to receive the response on.
   */
  public function requestEventCompanies(int $eventId, string $replyQueue): string {
    $correlationId = $this->generateMessageId();

    $payload = [
      'action'    => 'get_companies',
      'event_id'  => $eventId,
      'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
    ];

    $this->publish('event.companies.request', $payload, [
      'correlation_id' => $correlationId,
      'reply_to'       => $replyQueue,
    ]);

    return $correlationId;
  }

  /**
   * Generates a unique message ID.
   */
  protected function generateMessageId(): string {
    return sprintf(
      'drupal-%s-%s',
      date('Ymd-His'),
      substr(bin2hex(random_bytes(6)), 0, 12)
    );
  }

}
