<?php

namespace Drupal\Session_Management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class SessionEditForm extends FormBase {

  public function getFormId(): string {
    return 'session_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL): array {
    $form['sessionId'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
    ];

    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#required' => FALSE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $sessionId = $form_state->getValue('sessionId');
    $sessionName = $form_state->getValue('title');
    $location = $form_state->getValue('location') ?? NULL;

    $timestamp = date('c');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
      . '<SessionUpdated>'
      . '<sessionId>' . htmlspecialchars((string) $sessionId, ENT_XML1) . '</sessionId>'
      . '<sessionName>' . htmlspecialchars((string) $sessionName, ENT_XML1) . '</sessionName>'
      . '<changeType>updated</changeType>';

    if (!empty($location)) {
      $xml .= '<newLocation>' . htmlspecialchars((string) $location, ENT_XML1) . '</newLocation>';
    }

    $xml .= '<timestamp>' . $timestamp . '</timestamp>'
      . '</SessionUpdated>';

    $xsdPath = DRUPAL_ROOT . '/../xsd/Session.xsd';

    $dom = new \DOMDocument();
    $dom->loadXML($xml);

    if (!$dom->schemaValidate($xsdPath)) {
      $this->messenger()->addError($this->t('Generated XML does not match Session.xsd.'));
      return;
    }

    try {
      $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
      $port = (int) (getenv('RABBITMQ_PORT') ?: 5672);
      $user = getenv('RABBITMQ_USER') ?: 'guest';
      $pass = getenv('RABBITMQ_PASS') ?: 'guest';
      $exchange = getenv('RABBITMQ_EXCHANGE') ?: 'planning.topic';
      $routingKey = getenv('RABBITMQ_ROUTING_KEY') ?: 'planning.session.updated';

      $connection = new AMQPStreamConnection($host, $port, $user, $pass);
      $channel = $connection->channel();

      $channel->exchange_declare($exchange, 'topic', false, true, false, false);

      $msg = new AMQPMessage($xml, [
        'content_type' => 'text/xml',
        'delivery_mode' => 2,
      ]);

      $channel->basic_publish($msg, $exchange, $routingKey);

      $channel->close();
      $connection->close();

      $this->messenger()->addStatus($this->t('Session update sent to Planning.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Could not send the session update.'));
    }
  }

}