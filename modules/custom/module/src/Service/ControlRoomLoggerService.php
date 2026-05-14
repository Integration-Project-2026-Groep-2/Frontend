<?php

namespace Drupal\hello_world\Service;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Stuurt log events naar de Control Room via RabbitMQ.
 *
 * Exchange : logs.direct  (direct, durable)
 * Routing  : routing.log
 * XML      : <LogEvent><level/><timestamp/><service/><data/></LogEvent>
 *
 * Soft-fail: elke fout wordt gelogd in Drupal maar blokkeert nooit de request.
 */
class ControlRoomLoggerService {

  private const EXCHANGE    = 'logs.direct';
  private const ROUTING_KEY = 'routing.log';
  private const VALID_LEVELS = ['DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL', 'PANIC'];

  public function log(string $level, string $service, string $data): void {
    $level = strtoupper($level);
    if (!in_array($level, self::VALID_LEVELS, TRUE)) {
      $level = 'INFO';
    }

    try {
      $connection = new AMQPStreamConnection(
        $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
        (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
        $_ENV['RABBITMQ_USER'] ?? 'guest',
        $_ENV['RABBITMQ_PASS'] ?? 'guest',
        '/', FALSE, 'AMQPLAIN', NULL, 'en_US',
        1.0,
        1.0
      );
      $channel = $connection->channel();
      $channel->exchange_declare(self::EXCHANGE, 'direct', FALSE, TRUE, FALSE);

      $xml = sprintf(
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<LogEvent>' .
        '<level>%s</level>' .
        '<timestamp>%s</timestamp>' .
        '<service>%s</service>' .
        '<data>%s</data>' .
        '</LogEvent>',
        htmlspecialchars($level,   ENT_XML1, 'UTF-8'),
        date('Y-m-d\TH:i:s'),
        htmlspecialchars($service, ENT_XML1, 'UTF-8'),
        htmlspecialchars($data,    ENT_XML1, 'UTF-8')
      );

      $msg = new AMQPMessage($xml, [
        'content_type'  => 'application/xml',
        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
      ]);

      $channel->basic_publish($msg, self::EXCHANGE, self::ROUTING_KEY);
      $channel->close();
      $connection->close();
    }
    catch (\Throwable $e) {
      \Drupal::logger('controlroom_logger')->warning(
        'ControlRoom log publish mislukt (@service): @msg',
        ['@service' => $service, '@msg' => $e->getMessage()]
      );
    }
  }

  public function debug(string $service, string $data): void { $this->log('DEBUG', $service, $data); }
  public function info(string $service, string $data): void  { $this->log('INFO',  $service, $data); }
  public function warn(string $service, string $data): void  { $this->log('WARN',  $service, $data); }
  public function error(string $service, string $data): void { $this->log('ERROR', $service, $data); }
  public function fatal(string $service, string $data): void { $this->log('FATAL', $service, $data); }
  public function panic(string $service, string $data): void { $this->log('PANIC', $service, $data); }

}
