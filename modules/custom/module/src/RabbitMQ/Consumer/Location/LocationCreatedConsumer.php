<?php

namespace Drupal\hello_world\RabbitMQ\Consumer\Location;

use Drupal\hello_world\RabbitMQ\Validation\XsdRegistry;
use Drupal\hello_world\RabbitMQ\Validation\XsdValidator;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Verwerkt inkomende <LocationCreated>-berichten.
 *
 * Queue      : planning.location.created
 * Exchange   : frontend.topic
 * Routing key: frontend.location.created
 */
class LocationCreatedConsumer {

  private XsdValidator $validator;
  private \PhpAmqpLib\Channel\AMQPChannel $channel;
  private AMQPStreamConnection $connection;

  public function __construct(?XsdValidator $validator = NULL) {
    $this->validator = $validator ?? new XsdValidator(new XsdRegistry());
  }

  public function listen(string $queueName = 'planning.location.created'): void {
    echo "LocationCreatedConsumer luistert op '{$queueName}'...\n";
    \ControlRoomLogger::info('frontend-location-created', "LocationCreatedConsumer luistert op '{$queueName}'...");

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

    $this->channel->exchange_declare('frontend.topic', 'topic', false, true, false);
    $this->channel->queue_declare($queueName, false, true, false, false);
    $this->channel->queue_bind($queueName, 'frontend.topic', 'frontend.location.created');

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
          echo "Fout bij aanmaken locatie: " . $e->getMessage() . "\n";
          \ControlRoomLogger::error('frontend-location-created', 'Fout: ' . $e->getMessage());
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
      $this->validator->validate($xml, 'location_created');
    }
    catch (\RuntimeException $e) {
      \Drupal::logger('rabbitmq')->error(
        'LocationCreated XSD-validatie mislukt: @msg',
        ['@msg' => $e->getMessage()]
      );
      throw $e;
    }

    $data = $this->parse($xml);
    $this->upsertDrupalLocation($data);

    echo sprintf("[%s] Locatie aangemaakt: %s (Adres: %s)\n",
      date('H:i:s'), $data['roomName'], $data['address']);
    
    \ControlRoomLogger::info('frontend-location-created', sprintf(
      'Locatie aangemaakt: %s', $data['roomName']
    ));
  }

  private function parse(string $xml): array {
    $el = new \SimpleXMLElement($xml);
    return [
      'roomName' => (string) $el->roomName,
      'capacity' => (string) $el->capacity,
      'address'  => (string) $el->address,
    ];
  }

  private function upsertDrupalLocation(array $data): void {
    //TODO(Steven): add actual logic here
    echo "Locatie Update, Room: " . $data['roomName'] . " -> " . $data['address'] . "\n";
  }

}