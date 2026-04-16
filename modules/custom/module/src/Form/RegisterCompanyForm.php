<?php

namespace Drupal\hello_world\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RegisterCompanyForm extends FormBase {

  public function getFormId(): string {
    return 'register_company_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['contact_person'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact person information'),
    ];

    $form['contact_person']['contact_first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact person first name'),
      '#required' => TRUE,
    ];

    $form['contact_person']['contact_last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact person last name'),
      '#required' => TRUE,
    ];

    $form['contact_person']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['contact_person']['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Contact person phone number'),
      '#required' => FALSE,
    ];

    $form['company'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Company information'),
    ];

    $form['company']['company_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company Name'),
      '#required' => TRUE,
    ];

    $form['company']['vat_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('VAT Number'),
      '#required' => FALSE,
    ];

    $form['company']['street'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street'),
      '#required' => TRUE,
    ];

    $form['company']['house_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('House Number'),
      '#required' => TRUE,
    ];

    $form['company']['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#required' => TRUE,
    ];

    $form['company']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
    ];

    $form['company']['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#default_value' => 'NL',
      '#required' => TRUE,
    ];

    $form['gdpr_consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree to the GDPR terms'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register Company'),
    ];

    return $form;
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $phone = (string) $form_state->getValue('phone');
    if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone)) < 10) {
      $form_state->setErrorByName('phone', $this->t('Phone number must be at least 10 digits.'));
    }

    $email = (string) $form_state->getValue('email');
    $existingMail = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $email]);
    if (!empty($existingMail)) {
      $form_state->setErrorByName('email', $this->t('A user with this email already exists.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $xml = new \SimpleXMLElement('<CompanyCreated/>');
    $xml->addChild('name', (string) $form_state->getValue('company_name'));
    $xml->addChild('vatNumber', (string) $form_state->getValue('vat_number'));
    $xml->addChild('email', (string) $form_state->getValue('email'));

    $phone = (string) $form_state->getValue('phone');
    if ($phone !== '') {
      $xml->addChild('phone', $phone);
    }

    $xml->addChild('street', (string) $form_state->getValue('street'));
    $xml->addChild('houseNumber', (string) $form_state->getValue('house_number'));
    $xml->addChild('postalCode', (string) $form_state->getValue('postal_code'));
    $xml->addChild('city', (string) $form_state->getValue('city'));
    $xml->addChild('country', (string) $form_state->getValue('country'));

    $connection = new AMQPStreamConnection(
      $_ENV['RABBITMQ_HOST'] ?? '',
      5672,
      $_ENV['RABBITMQ_USER'] ?? '',
      $_ENV['RABBITMQ_PASS'] ?? ''
    );

    $channel = $connection->channel();

    $exchange = '';
    $routing_key = '';

    $msg = new AMQPMessage($xml->asXML(), [
      'content_type' => 'text/xml',
      'delivery_mode' => 2,
    ]);

    $channel->basic_publish($msg, $exchange, $routing_key);

    $channel->close();
    $connection->close();

    $this->messenger()->addStatus($this->t('Company registration successful!'));
  }

}