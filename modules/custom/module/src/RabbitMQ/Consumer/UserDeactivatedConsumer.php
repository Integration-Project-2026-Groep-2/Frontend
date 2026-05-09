<?php

namespace Drupal\hello_world\RabbitMQ\Consumer;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Verwerkt inkomende <UserDeactivated>-berichten (Contract 22 / Contract 26).
 *
 * Queue  : frontend.user.deactivated
 * Exchange: contact.topic
 * Routing key (inbound): crm.user.deactivated
 *
 * Valideert berichten tegen xsd/user_deactivated.xsd.
 * Bij een geldig bericht wordt de Drupal-gebruiker gedeactiveerd (status = 0).
 */
class UserDeactivatedConsumer {

  private \PhpAmqpLib\Channel\AMQPChannel $channel;
  private AMQPStreamConnection $connection;

  /** Absoluut pad naar het standalone XSD voor UserDeactivated. */
  private string $xsdPath;

  public function __construct() {
    // Absoluut pad naar het XSD-bestand in de container.
    $this->xsdPath = '/opt/drupal/xsd/user_deactivated.xsd';
  }

  public function listen(string $queueName = 'frontend.user.deactivated'): void {
    echo "UserDeactivatedConsumer luistert op '{$queueName}'...\n";

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

    // Queue declareren (duurzaam)
    $this->channel->queue_declare($queueName, false, true, false, false);

    // Queue binden aan exchange met CRM routing key
    $this->channel->queue_bind($queueName, 'contact.topic', 'crm.user.deactivated');

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
          echo "Fout: " . $e->getMessage() . "\n";
        }
      }
    );

    while (count($this->channel->callbacks)) {
      try {
        $this->channel->wait(null, false, 60);
      }
      catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
        // Normaal — geen berichten binnen 60s, gewoon verder wachten.
      }
    }
  }

  private function handleMessage(AMQPMessage $msg): void {
    $xml = $msg->getBody();

    // XSD-validatie tegen user_deactivated.xsd.
    $this->validateXml($xml);

    $data = $this->parse($xml);
    $this->deactivateDrupalUser($data);

    echo sprintf(
      "[%s] User gedeactiveerd: <%s> (crmId: %s)\n",
      date('H:i:s'), $data['email'], $data['crmId']
    );
  }

  /**
   * Valideert de XML-string tegen user_deactivated.xsd.
   *
   * @throws \RuntimeException Bij een ontbrekend XSD-bestand of validatiefout.
   */
  private function validateXml(string $xml): void {
    if (!file_exists($this->xsdPath)) {
      throw new \RuntimeException(
        'user_deactivated.xsd niet gevonden op: ' . $this->xsdPath
      );
    }

    $dom = new \DOMDocument();
    $dom->loadXML($xml, LIBXML_NOENT | LIBXML_DTDLOAD);

    if ($dom->documentElement->localName !== 'UserDeactivated') {
      throw new \RuntimeException(sprintf(
        'Verkeerd root-element: verwacht <UserDeactivated>, kreeg <%s>.',
        $dom->documentElement->localName
      ));
    }

    libxml_use_internal_errors(true);
    $valid  = $dom->schemaValidate($this->xsdPath);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors(false);

    if (!$valid) {
      $messages = array_map(
        fn(\LibXMLError $e) => trim($e->message) . ' (regel ' . $e->line . ')',
        $errors
      );
      $errorText = implode(PHP_EOL, $messages);

      \Drupal::logger('rabbitmq')->error(
        'UserDeactivated XSD-validatie mislukt: @msg',
        ['@msg' => $errorText]
      );

      throw new \RuntimeException(
        'XSD-validatie mislukt voor <UserDeactivated>:' . PHP_EOL . $errorText
      );
    }
  }

  private function parse(string $xml): array {
    $el = new \SimpleXMLElement($xml);
    return [
      'crmId'         => (string) $el->id,           // CRM Master UUID → field_crm_id
      'email'         => (string) $el->email,
      'deactivatedAt' => (string) $el->deactivatedAt,
    ];
  }

  private function deactivateDrupalUser(array $data): void {
    /** @var \Drupal\user\UserStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('user');

    // Controleer of field_crm_id bestaat voordat we er op zoeken.
    $accounts = [];
    $fields   = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('user', 'user');
    if (isset($fields['field_crm_id'])) {
      $accounts = $storage->loadByProperties(['field_crm_id' => $data['crmId']]);
    }
    if (!$accounts) {
      $accounts = $storage->loadByProperties(['mail' => $data['email']]);
    }

    if (!$accounts) {
      \Drupal::logger('rabbitmq')->warning(
        'UserDeactivated: geen Drupal-gebruiker gevonden voor crmId @id / mail @mail.',
        ['@id' => $data['crmId'], '@mail' => $data['email']]
      );
      // Geen gebruiker gevonden — bericht toch ack'en zodat het niet blijft
      // hangen; de CRM is leidend en de gebruiker bestaat gewoon niet hier.
      return;
    }

    $account = reset($accounts);
    $account->set('status', 0);
    $account->save();

    \Drupal::logger('rabbitmq')->info(
      'Gebruiker @mail (crmId @id) gedeactiveerd via CRM.',
      ['@mail' => $data['email'], '@id' => $data['crmId']]
    );
  }

}
