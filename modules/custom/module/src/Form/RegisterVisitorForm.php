<?php
namespace Drupal\hello_world\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RegisterVisitorForm extends FormBase {

  public function getFormId(): string {
    return 'register_visitor_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];
    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];
    $form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#required' => FALSE,
    ];
    $form['gdpr_consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree to the GDPR terms'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $phone = $form_state->getValue('phone');
    if (!empty($phone) && strlen($phone) < 10) {
      $form_state->setErrorByName('phone', $this->t('Phone number must be at least 10 digits.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $registrationId = uniqid();
    $sessionId = uniqid();
    $gdpr = $form_state->getValue('gdpr_consent') ? 'true' : 'false';

    $xml = new \SimpleXMLElement('<Registration/>');
    $xml->addChild('registrationId', $registrationId);
    $xml->addChild('firstName', $form_state->getValue('first_name'));
    $xml->addChild('lastName', $form_state->getValue('last_name'));
    $xml->addChild('email', $form_state->getValue('email'));
    $xml->addChild('sessionId', $sessionId);
    $xml->addChild('role', 'VISITOR');
    $xml->addChild('gdprConsent', $gdpr);
    $phone = $form_state->getValue('phone');
    if (!empty($phone)) {
      $xml->addChild('phone', $phone);
    }

    $connection = new AMQPStreamConnection(
      $_ENV['RABBITMQ_HOST'], 5672,
      $_ENV['RABBITMQ_USER'], $_ENV['RABBITMQ_PASS']
    );
    $channel = $connection->channel();
    $channel->exchange_declare('user.exchange', 'topic', false, true, false);
    $msg = new AMQPMessage(
      $xml->asXML(),
      ['content_type' => 'text/xml', 'delivery_mode' => 2]
    );
    $channel->basic_publish($msg, 'user.exchange', 'user.topic');
    $channel->close();
    $connection->close();

    $this->messenger()->addMessage($this->t('Registration successful!'));
  }
}