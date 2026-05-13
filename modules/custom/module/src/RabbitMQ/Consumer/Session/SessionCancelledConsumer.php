<?php

namespace Drupal\hello_world\RabbitMQ\Consumer\Session;

use Drupal\hello_world\RabbitMQ\Validation\XsdRegistry;
use Drupal\hello_world\RabbitMQ\Validation\XsdValidator;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Verwerkt inkomende <SessionCancelled>-berichten.
 *
 * Queue    : planning.session.cancelled
 * Exchange : planning.topic
 * Routing key: frontend.session.cancelled
 */
class SessionCancelledConsumer {

  private XsdValidator $validator;
  private \PhpAmqpLib\Channel\AMQPChannel $channel;
  private AMQPStreamConnection $connection;

  public function __construct(?XsdValidator $validator = NULL) {
    $this->validator = $validator ?? new XsdValidator(new XsdRegistry());
  }

  public function listen(string $queueName = 'planning.session.cancelled'): void {
    echo "SessionCancelledConsumer luistert op '{$queueName}'...\n";
    \ControlRoomLogger::info('frontend-session-cancelled', "SessionCancelledConsumer luistert op '{$queueName}'...");

    $this->connection = new AMQPStreamConnection(
      $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
      (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
      $_ENV['RABBITMQ_USER'] ?? 'guest',
      $_ENV['RABBITMQ_PASS'] ?? 'guest',
      '/',
      false,
      'AMQPLAIN',
      null,
      'en_US',
      3.0,
      130,
      null,
      false,
      60
    );
    $this->channel = $this->connection->channel();

    $this->channel->exchange_declare('planning.topic', 'topic', false, true, false);
    $this->channel->queue_declare($queueName, false, true, false, false);
    $this->channel->queue_bind($queueName, 'planning.topic', 'frontend.session.cancelled');

    $this->channel->basic_qos(null, 1, null);
    $this->channel->basic_consume(
      $queueName, '', false, false, false, false,
      function (AMQPMessage $msg) {
        try {
          $this->handleMessage($msg);
          $this->channel->basic_ack($msg->delivery_info['delivery_tag']);
        }
        catch (\Throwable $e) {
          $this->channel->basic_nack($msg->delivery_info['delivery_tag'], false, false);
          echo "Fout bij annuleren sessie: " . $e->getMessage() . "\n";
          \ControlRoomLogger::error('frontend-session-cancelled', 'Fout: ' . $e->getMessage());
        }
      }
    );

    while (count($this->channel->callbacks)) {
      try {
        $this->channel->wait(null, false, 60);
      }
      catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
      }
    }
  }

  private function handleMessage(AMQPMessage $msg): void {
    $xml = $msg->getBody();

    try {
      $this->validator->validate($xml, 'session_cancelled');
    }
    catch (\RuntimeException $e) {
      \Drupal::logger('rabbitmq')->error(
        'SessionCancelled XSD-validatie mislukt: @msg',
        ['@msg' => $e->getMessage()]
      );
      throw $e;
    }

    $data = $this->parse($xml);
    $this->upsertDrupalSession($data);

    echo sprintf("[%s] Sessie geannuleerd: ID %s (Status: %s)\n",
      date('H:i:s'), $data['sessionId'], $data['status']);
    
    \ControlRoomLogger::info('frontend-session-cancelled', sprintf(
      'Sessie geannuleerd: ID %s', $data['sessionId']
    ));
  }

  private function parse(string $xml): array {
    $el = new \SimpleXMLElement($xml);
    return [
      'sessionId' => (string) $el->sessionId,
      'status'    => (string) $el->status,
    ];
  }

  private function upsertDrupalSession(array $data): void {
    //TODO(Steven): add actual logic here
    echo "Sessie Status Update, ID: " . $data['sessionId'] . " -> " . $data['status'];
  }

}