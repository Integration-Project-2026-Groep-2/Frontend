<?php

namespace Drupal\hello_world\RabbitMQ\Publisher;

use Drupal\hello_world\RabbitMQ\Message\MessageInterface;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;
use Drupal\user\UserInterface;

class UserCreatedPublisher implements MessageInterface {

  public function __construct(private readonly UserInterface $user) {}

  public function toXml(): string {
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = TRUE;
    
    // We use the same message as registration (frontend-contract.xsd)
    $ns = 'urn:frontend:crm:contract';
    $root = $dom->createElementNS($ns, 'Registration');
    $dom->appendChild($root);

    $crmId = $this->user->hasField('field_crm_id') && !$this->user->get('field_crm_id')->isEmpty() 
      ? $this->user->get('field_crm_id')->value 
      : uniqid('reg_', TRUE);
      
    $root->appendChild($dom->createElementNS($ns, 'registrationId', htmlspecialchars($crmId)));

    $firstName = $this->user->hasField('field_first_name') ? $this->user->get('field_first_name')->value : '';
    $root->appendChild($dom->createElementNS($ns, 'firstName', htmlspecialchars($firstName ?? '')));

    $lastName = $this->user->hasField('field_surname') ? $this->user->get('field_surname')->value : '';
    $root->appendChild($dom->createElementNS($ns, 'lastName', htmlspecialchars($lastName ?? '')));

    $root->appendChild($dom->createElementNS($ns, 'email', htmlspecialchars($this->user->getEmail() ?? '')));

    // Role mapping
    $roles = $this->user->getRoles();
    $role = 'visitor';
    if (in_array('administrator', $roles)) $role = 'sysadmin';
    elseif (in_array('event_manager', $roles)) $role = 'eventbeheerder';
    elseif (in_array('speaker', $roles)) $role = 'spreker';
    elseif (in_array('company', $roles)) $role = 'company_contact';
    elseif (in_array('kassa', $roles)) $role = 'kassamedewerker';

    $root->appendChild($dom->createElementNS($ns, 'role', $role));

    $gdpr = $this->user->hasField('field_gdpr_consent') && $this->user->get('field_gdpr_consent')->value ? 'true' : 'false';
    $root->appendChild($dom->createElementNS($ns, 'gdprConsent', $gdpr));

    if ($this->user->hasField('field_phone') && !$this->user->get('field_phone')->isEmpty()) {
      $root->appendChild($dom->createElementNS($ns, 'phone', htmlspecialchars($this->user->get('field_phone')->value)));
    }

    return $dom->saveXML();
  }

  public function getRoutingKey(): string {
    return 'frontend.registration.created';
  }

  public function getType(): string {
    // This maps to Registration root element and frontend-contract.xsd in XsdRegistry
    return 'registration';
  }

  public function publish(): void {
    try {
      $client = RabbitMQClient::fromEnv();
      $client->publish($this);
    } catch (\Exception $e) {
      \Drupal::logger('rabbitmq')->error(
        'UserCreatedPublisher mislukt: @err', ['@err' => $e->getMessage()]
      );
    }
  }

}
