<?php

namespace Drupal\hello_world\RabbitMQ\Consumer\Session;

use Drupal\hello_world\RabbitMQ\Validation\XsdRegistry;
use Drupal\hello_world\RabbitMQ\Validation\XsdValidator;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Verwerkt inkomende <SessionCreated>-berichten.
 *
 * Queue   : planning.session.created
 * Exchange: planning.topic
 * Routing key: frontend.session.created
 */
class SessionCreatedConsumer {

  private XsdValidator $validator;
  private \PhpAmqpLib\Channel\AMQPChannel $channel;
  private AMQPStreamConnection $connection;

  public function __construct(?XsdValidator $validator = NULL) {
    $this->validator = $validator ?? new XsdValidator(new XsdRegistry());
  }

  public function listen(string $queueName = 'planning.session.created'): void {
    echo "SessionCreatedConsumer luistert op '{$queueName}'...\n";
    \ControlRoomLogger::info('frontend-session-created', "SessionCreatedConsumer luistert op '{$queueName}'...");

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

    // Exchange declareren
    $this->channel->exchange_declare('planning.topic', 'topic', false, true, false);

    // Queue declareren (duurzaam)
    $this->channel->queue_declare($queueName, false, true, false, false);

    // Queue binden aan exchange met planning routing key
    $this->channel->queue_bind($queueName, 'planning.topic', 'frontend.session.created');

    $this->channel->basic_qos(null, 1, null);
    $this->channel->basic_consume(
      $queueName, '', false, false, false, false,
      function (AMQPMessage $msg) {
        try {
          $this->handleMessage($msg);
          $this->channel->basic_ack($msg->delivery_info['delivery_tag']);
        }
        catch (\Throwable $e) {
          // Bij fouten: nack zonder requeue om loops te voorkomen bij validatiefouten
          $this->channel->basic_nack($msg->delivery_info['delivery_tag'], false, false);
          echo "Fout bij verwerken sessie: " . $e->getMessage() . "\n";
          \ControlRoomLogger::error('frontend-session-created', 'Fout: ' . $e->getMessage());
        }
      }
    );

    while (count($this->channel->callbacks)) {
      try {
        $this->channel->wait(null, false, 60);
      }
      catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
        // Timeout is normaal
      }
    }
  }

  private function handleMessage(AMQPMessage $msg): void {
    $xml = $msg->getBody();

    try {
      // Gebruik een specifiek XSD-contract voor sessies
      $this->validator->validate($xml, 'session_created');
    }
    catch (\RuntimeException $e) {
      \Drupal::logger('rabbitmq')->error(
        'SessionCreated XSD-validatie mislukt: @msg',
        ['@msg' => $e->getMessage()]
      );
      throw $e;
    }

    $data = $this->parse($xml);
    $this->upsertDrupalSession($data);

    echo sprintf("[%s] Sessie aangemaakt/bijgewerkt: %s (%s)\n",
      date('H:i:s'), $data['title'], $data['sessionId']);
    \ControlRoomLogger::info('frontend-session-created', sprintf(
      'Sessie verwerkt: %s (ID: %s)', $data['title'], $data['sessionId']
    ));
  }

  private function parse(string $xml): array {
    $el = new \SimpleXMLElement($xml);
    return [
      'sessionId' => (string) $el->sessionId,
      'title'     => (string) $el->title,
      'date'      => (string) $el->date,
      'startTime' => (string) $el->startTime,
      'endTime'   => (string) $el->endTime,
      'location'  => (string) $el->location,
      'status'    => (string) $el->status,
      'capacity'  => (int) $el->capacity,
    ];
  }

  private function upsertDrupalSession(array $data): void {
    //TODO(Steven): add actual logic here
    echo   $data['title'] . ", ID: " . $data['sessionID'];
  }
}