<?php

namespace Drupal\hello_world\RabbitMQ\Consumer;

use Drupal\hello_world\RabbitMQ\RabbitMQClient;
use Drupal\hello_world\RabbitMQ\Validation\XsdRegistry;
use Drupal\hello_world\RabbitMQ\Validation\XsdValidator;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Verwerkt inkomende <UserUpdated>-berichten (Contract 18 / Contract 25)
 * en slaat ze op in de Drupal-database.
 *
 * Contract 18: CRM → consumers (outbound user update)
 * Contract 25: Facturatie → CRM (routing key: facturatie.user.updated)
 *
 * Beide gebruiken hetzelfde <UserUpdated> root-element (xs:all volgorde-vrij).
 *
 * Drupal field mapping (pas aan aan jouw veldnamen):
 *   field_first_name, field_last_name, field_phone,
 *   field_street, field_house_number, field_postal_code,
 *   field_city, field_country, field_role, field_company_id,
 *   field_badge_code, field_is_active, field_gdpr_consent
 */
class UserUpdateConsumer {

  private RabbitMQClient $client;
  private XsdValidator $validator;

  public function __construct(
    ?RabbitMQClient $client    = NULL,
    ?XsdValidator   $validator = NULL
  ) {
    $this->client    = $client    ?? RabbitMQClient::fromEnv();
    $this->validator = $validator ?? new XsdValidator(new XsdRegistry());
  }

  /**
   * Start een blokkerende consumer op de opgegeven queue.
   */
  public function listen(string $queueName = 'crm.user.updated'): void {
    echo "UserUpdateConsumer luistert op '{$queueName}'…\n";

    $this->client->consume($queueName, function (AMQPMessage $msg) {
      try {
        $this->handleMessage($msg);
        $msg->ack();
      }
      catch (\Throwable $e) {
        // Nack zonder requeue → dead-letter exchange vangt het op.
        $msg->nack(FALSE);
        \Drupal::logger('hello_world')->error(
          'UserUpdateConsumer fout: @msg', ['@msg' => $e->getMessage()]
        );
      }
    });
  }

  // ---------------------------------------------------------------------------

  private function handleMessage(AMQPMessage $msg): void {
    $xml = $msg->getBody();

    // Valideer tegen <UserUpdated> in de master XSD.
    $this->validator->validate($xml, 'user_updated');

    $data = $this->parse($xml);
    $this->upsertDrupalUser($data);

    echo sprintf(
      "[%s] User bijgewerkt: %s %s <%s> (rol: %s, actief: %s)\n",
      date('H:i:s'),
      $data['firstName'], $data['lastName'], $data['email'],
      $data['role'],
      $data['isActive'] ? 'ja' : 'nee'
    );
  }

  /**
   * Parst <UserUpdated> XML naar een array.
   * xs:all => element-volgorde is willekeurig, dus we lezen via elementnaam.
   *
   * @return array{
   *   id: string, email: string, firstName: string, lastName: string,
   *   phone: ?string, street: ?string, houseNumber: ?string,
   *   postalCode: ?string, city: ?string, country: ?string,
   *   role: string, companyId: ?string, badgeCode: ?string,
   *   isActive: bool, gdprConsent: ?bool, updatedAt: string
   * }
   */
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
        : NULL,
      'updatedAt'   => (string) $el->updatedAt,
    ];
  }

  /**
   * Laadt een bestaand Drupal-account op e-mail en updatet de velden,
   * of maakt een nieuw geblokkeerd account aan als er geen bestaat.
   */
  private function upsertDrupalUser(array $data): void {
    /** @var \Drupal\user\UserStorageInterface $storage */
    $storage  = \Drupal::entityTypeManager()->getStorage('user');
    $accounts = $storage->loadByProperties(['mail' => $data['email']]);
    $account  = $accounts ? reset($accounts) : NULL;

    if ($account === NULL) {
      $account = $storage->create([
        'name'   => $data['email'],
        'mail'   => $data['email'],
        'status' => 0,  // geblokkeerd tot verificatie
      ]);
    }

    // ── Veld-mapping → pas aan aan jouw echte Drupal-veldnamen ─────────── //
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

    if ($data['gdprConsent'] !== NULL) {
      $this->setField($account, 'field_gdpr_consent', $data['gdprConsent']);
    }

    $account->save();
  }

  /**
   * Stelt een veldwaarde in als het veld bestaat op de entity.
   * Voorkomt fatale fouten bij ontbrekende veldconfiguraties.
   */
  private function setField(object $entity, string $field, mixed $value): void {
    if ($value !== NULL && $entity->hasField($field)) {
      $entity->set($field, $value);
    }
  }

  /**
   * Geeft NULL terug als een SimpleXML-element leeg of afwezig is.
   */
  private function nullable(\SimpleXMLElement|NULL $el): ?string {
    if ($el === NULL) {
      return NULL;
    }
    $val = (string) $el;
    return $val === '' ? NULL : $val;
  }

}
