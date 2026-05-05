<?php

namespace Drupal\hello_world\RabbitMQ\Consumer;

use Drupal\hello_world\RabbitMQ\RabbitMQClient;
use Drupal\hello_world\RabbitMQ\Validation\XsdRegistry;
use Drupal\hello_world\RabbitMQ\Validation\XsdValidator;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Verwerkt inkomende <UserUpdated>-berichten (Contract 18 / Contract 25).
 */
class UserUpdateConsumer {

  private XsdValidator $validator;
  private \PhpAmqpLib\Channel\AMQPChannel $channel;
  private AMQPStreamConnection $connection;

  public function __construct(?XsdValidator $validator = NULL) {
    $this->validator = $validator ?? new XsdValidator(new XsdRegistry());
  }

  public function listen(string $queueName = 'crm.user.updated'): void {
    echo "UserUpdateConsumer luistert op '{$queueName}'...\n";

    $this->connection = new AMQPStreamConnection(
      $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
      (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
      $_ENV['RABBITMQ_USER'] ?? 'guest',
      $_ENV['RABBITMQ_PASS'] ?? 'guest',
      '/',        // vhost
      false,      // insist
      'AMQPLAIN', // login method
      null,       // login response
      'en_US',    // locale
      3.0,        // connection timeout
      130,        // read/write timeout — minstens 2x heartbeat (60)
      null,       // context
      false,      // keepalive
      60          // heartbeat
    );

    $this->channel = $this->connection->channel();

    // Exchange declareren
    $this->channel->exchange_declare('contact.topic', 'topic', false, true, false);

    // Queue declareren
    $this->channel->queue_declare($queueName, false, true, false, false);

    // Queue binden aan exchange met de juiste routing keys
    $this->channel->queue_bind($queueName, 'contact.topic', 'facturatie.user.updated');
    $this->channel->queue_bind($queueName, 'contact.topic', 'crm.user.updated');

    $this->channel->basic_qos(null, 1, null);
    $this->channel->basic_consume(
      $queueName, '', false, false, false, false,
      function (AMQPMessage $msg) {
        try {
          $this->handleMessage($msg);
          $msg->ack();
        }
        catch (\Throwable $e) {
          $this->channel->basic_nack($msg->delivery_info['delivery_tag'], false, false);
          echo "Fout: " . $e->getMessage() . "\n";
        }
      }
    );

    while (count($this->channel->callbacks)) {
      try {
        $this->channel->wait(null, false, 60);
        echo "[" . date('H:i:s') . "] Wachten op berichten...\n";
      }
      catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
        // Normaal — geen berichten binnen 60s, gewoon verder wachten.
        echo "[" . date('H:i:s') . "] Wachten op berichten...\n";
      }
    }
  }

  private function handleMessage(AMQPMessage $msg): void {
    $xml  = $msg->getBody();
    $data = $this->parse($xml);
    $this->upsertDrupalUser($data);

    echo sprintf(
      "[%s] User bijgewerkt: %s %s <%s>\n",
      date('H:i:s'), $data['firstName'], $data['lastName'], $data['email']
    );
  }

  private function parse(string $xml): array {
    $el = new \SimpleXMLElement($xml);
    return [
      'id'          => (string) $el->id,
      'email'       => (string) $el->email,
      'firstName'   => (string) $el->firstName,
      'lastName'    => (string) $el->lastName,
      'phone'       => $this->nullable($el->phone),
      'street'      => $this->nullable($el->street),
      'houseNumber' => $this->nullable($el->houseNumber),
      'postalCode'  => $this->nullable($el->postalCode),
      'city'        => $this->nullable($el->city),
      'country'     => $this->nullable($el->country),
      'role'        => (string) $el->role,
      'companyId'   => $this->nullable($el->companyId),
      'badgeCode'   => $this->nullable($el->badgeCode),
      'isActive'    => filter_var((string) $el->isActive, FILTER_VALIDATE_BOOLEAN),
      'gdprConsent' => isset($el->gdprConsent)
        ? filter_var((string) $el->gdprConsent, FILTER_VALIDATE_BOOLEAN)
        : null,
      'updatedAt'   => (string) $el->updatedAt,
    ];
  }

  private function upsertDrupalUser(array $data): void {
    /** @var \Drupal\user\UserStorageInterface $storage */
    $storage  = \Drupal::entityTypeManager()->getStorage('user');
    $accounts = $storage->loadByProperties(['mail' => $data['email']]);
    $account  = $accounts ? reset($accounts) : null;

    if ($account === null) {
      $account = $storage->create([
        'name'   => $data['email'],
        'mail'   => $data['email'],
        'status' => $data['isActive'] ? 1 : 0,
      ]);
      $account->addRole('visitor');
    }
    else {
      $account->set('status', $data['isActive'] ? 1 : 0);
    }

    $this->setField($account, 'field_first_name',   $data['firstName']);
    $this->setField($account, 'field_last_name',    $data['lastName']);
    $this->setField($account, 'field_phone',        $data['phone']);
    $this->setField($account, 'field_street',       $data['street']);
    $this->setField($account, 'field_house_number', $data['houseNumber']);
    $this->setField($account, 'field_postal_code',  $data['postalCode']);
    $this->setField($account, 'field_city',         $data['city']);
    $this->setField($account, 'field_country',      $data['country']);
    $this->setField($account, 'field_role',         $data['role']);
    $this->setField($account, 'field_company_id',   $data['companyId']);
    $this->setField($account, 'field_badge_code',   $data['badgeCode']);
    $this->setField($account, 'field_is_active',    $data['isActive']);

    if ($data['gdprConsent'] !== null) {
      $this->setField($account, 'field_gdpr_consent', $data['gdprConsent']);
    }

    $account->save();
  }

  private function setField(object $entity, string $field, mixed $value): void {
    if ($value !== null && $entity->hasField($field)) {
      $entity->set($field, $value);
    }
  }

  private function nullable(\SimpleXMLElement|null $el): ?string {
    if ($el === null) return null;
    $val = (string) $el;
    return $val === '' ? null : $val;
  }

}
