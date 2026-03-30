<?php

namespace Drupal\rabbitmq_integration\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Channel\AMQPChannel;

/**
 * Manages the RabbitMQ connection and channels.
 *
 * This is the single point of truth for the AMQP connection.
 * All other services receive this via dependency injection.
 */
class RabbitMQConnectionManager {

  /**
   * The active AMQP connection.
   */
  protected ?AMQPStreamConnection $connection = null;

  /**
   * Active channels keyed by channel ID.
   *
   * @var AMQPChannel[]
   */
  protected array $channels = [];

  /**
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Returns (or creates) the active AMQP connection.
   *
   * @throws \Exception If the connection cannot be established.
   */
  public function getConnection(): AMQPStreamConnection {
    if ($this->connection !== null && $this->connection->isConnected()) {
      return $this->connection;
    }

    $config = $this->configFactory->get('rabbitmq_integration.settings');

    $host    = $config->get('host') ?? 'localhost';
    $port    = (int) ($config->get('port') ?? 5672);
    $user    = $config->get('username') ?? 'guest';
    $pass    = $config->get('password') ?? 'guest';
    $vhost   = $config->get('vhost') ?? '/';
    $timeout = (float) ($config->get('connection_timeout') ?? 3.0);
    $rwtime  = (float) ($config->get('read_write_timeout') ?? 3.0);
    $ssl     = (bool) ($config->get('ssl_enabled') ?? false);

    try {
      if ($ssl) {
        $this->connection = new AMQPSSLConnection(
          $host, $port, $user, $pass, $vhost,
          ['verify_peer' => true],
          ['connection_timeout' => $timeout, 'read_write_timeout' => $rwtime]
        );
      }
      else {
        $this->connection = new AMQPStreamConnection(
          $host, $port, $user, $pass, $vhost,
          false, 'AMQPLAIN', null, 'en_US',
          $timeout, $rwtime
        );
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rabbitmq_integration')->error(
        'Failed to connect to RabbitMQ at @host:@port — @msg',
        ['@host' => $host, '@port' => $port, '@msg' => $e->getMessage()]
      );
      throw $e;
    }

    return $this->connection;
  }

  /**
   * Returns an AMQP channel, creating a new one if needed.
   *
   * @param int|null $channelId  Optional specific channel ID.
   */
  public function getChannel(?int $channelId = null): AMQPChannel {
    $key = $channelId ?? 'default';

    if (!isset($this->channels[$key]) || !$this->channels[$key]->is_open()) {
      $this->channels[$key] = $this->getConnection()->channel($channelId);
    }

    return $this->channels[$key];
  }

  /**
   * Declares the main exchange on a channel.
   *
   * Call this before publishing or binding queues. Idempotent.
   */
  public function declareExchange(AMQPChannel $channel): void {
    $config = $this->configFactory->get('rabbitmq_integration.settings');

    $channel->exchange_declare(
      exchange:  $config->get('exchange_name') ?? 'drupal_exchange',
      type:      $config->get('exchange_type') ?? 'topic',
      passive:   false,
      durable:   (bool) ($config->get('exchange_durable') ?? true),
      auto_delete: false,
    );
  }

  /**
   * Closes all channels and the connection cleanly.
   */
  public function closeConnection(): void {
    foreach ($this->channels as $channel) {
      try {
        if ($channel->is_open()) {
          $channel->close();
        }
      }
      catch (\Exception $e) {
        // Ignore close errors; we're shutting down anyway.
      }
    }
    $this->channels = [];

    try {
      if ($this->connection?->isConnected()) {
        $this->connection->close();
      }
    }
    catch (\Exception $e) {
      // Ignore.
    }
    $this->connection = null;
  }

  /**
   * Ensure connection is closed when the service is destroyed.
   */
  public function __destruct() {
    $this->closeConnection();
  }

}
