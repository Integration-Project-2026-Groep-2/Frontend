<?php

namespace Drupal\hello_world\RabbitMQ\Publisher;

use Drupal\hello_world\RabbitMQ\Message\MessageInterface;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;
use Drupal\user\UserInterface;

class UserUpdatedPublisher implements MessageInterface {

  public function __construct(
    private readonly UserInterface $user,
    private readonly string $changeType = 'updated'
  ) {}

  public function toXml(): string {
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = TRUE;
    
    $ns = 'urn:frontend:crm:contract';
    $root = $dom->createElementNS($ns, 'RegistrationChange');
    $dom->appendChild($root);

    $crmId = $this->user->hasField('field_crm_id') && !$this->user->get('field_crm_id')->isEmpty() 
      ? $this->user->get('field_crm_id')->value 
      : '';
      
    if (!empty($crmId)) {
      $root->appendChild($dom->createElementNS($ns, 'registrationId', htmlspecialchars($crmId)));
    }
    
    $root->appendChild($dom->createElementNS($ns, 'email', htmlspecialchars($this->user->getEmail() ?? '')));
    $root->appendChild($dom->createElementNS($ns, 'changeType', $this->changeType));

    $updatedFields = $dom->createElementNS($ns, 'updatedFields');
    $hasFields = false;

    $firstName = $this->user->hasField('field_first_name') ? $this->user->get('field_first_name')->value : '';
    if (!empty($firstName)) {
      $updatedFields->appendChild($dom->createElementNS($ns, 'firstName', htmlspecialchars($firstName)));
      $hasFields = true;
    }

    $lastName = $this->user->hasField('field_surname') ? $this->user->get('field_surname')->value : '';
    if (!empty($lastName)) {
      $updatedFields->appendChild($dom->createElementNS($ns, 'lastName', htmlspecialchars($lastName)));
      $hasFields = true;
    }

    if ($this->user->hasField('field_phone') && !$this->user->get('field_phone')->isEmpty()) {
      $updatedFields->appendChild($dom->createElementNS($ns, 'phone', htmlspecialchars($this->user->get('field_phone')->value)));
      $hasFields = true;
    }

    $roles = $this->user->getRoles();
    $role = 'VISITOR';
    if (in_array('administrator', $roles)) $role = 'SYSADMIN';
    elseif (in_array('event_manager', $roles)) $role = 'EVENTBEHEERDER';
    elseif (in_array('speaker', $roles)) $role = 'SPREKER';
    elseif (in_array('company', $roles)) $role = 'COMPANY_CONTACT';
    elseif (in_array('kassa', $roles)) $role = 'KASSAMEDEWERKER';

    $updatedFields->appendChild($dom->createElementNS($ns, 'role', $role));
    $hasFields = true;

    if ($hasFields) {
      $root->appendChild($updatedFields);
    }

    return $dom->saveXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.registration.updated';
  }

  public function getType(): string {
    return 'registration_change';
  }

  public function publish(): void {
    try {
      $client = RabbitMQClient::fromEnv();
      $client->publish($this);
    } catch (\Exception $e) {
      \Drupal::logger('rabbitmq')->error(
        'UserUpdatedPublisher mislukt: @err', ['@err' => $e->getMessage()]
      );
    }
  }

}
