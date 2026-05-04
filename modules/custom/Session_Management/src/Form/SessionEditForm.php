<?php

namespace Drupal\Session_Management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Session_Management\RabbitMQ\Message\SessionListRequest;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class SessionEditForm extends FormBase {

  public function getFormId() {
    return 'session_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $id = NULL) {
    #mock data
    $sessions = [
      '1' => [
        'title' => 'PHP Security Basics',
        'location' => 'Room A',
        'speaker' => 'Acme Corp',
        'capacity' => 50,
      ],
      '2' => [
        'title' => 'Web Performance Tips',
        'location' => 'Room B',
        'speaker' => 'Tech Solutions',
        'capacity' => 30,
      ],
    ];

    $current = $sessions[$id] ?? [
      'title' => '',
      'location' => '',
      'speaker' => '',
      'capacity' => '',
    ];

    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $current['title'],
      '#required' => TRUE,
    ];

    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#default_value' => $current['location'],
      '#required' => TRUE,
    ];

    $form['speaker'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Speaker'),
      '#default_value' => $current['speaker'],
    ];

    $form['capacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Capacity'),
      '#default_value' => $current['capacity'],
      '#min' => 1,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save changes'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $request = new SessionListRequest();
      $xml = $request->toXml();

      $host = getenv('RABBITMQ_HOST') ?: 'localhost';
      $port = (int) (getenv('RABBITMQ_PORT') ?: 5672);
      $user = getenv('RABBITMQ_USER') ?: 'guest';
      $pass = getenv('RABBITMQ_PASS') ?: 'guest';
      $exchange = getenv('RABBITMQ_EXCHANGE') ?: 'planning.topic';
      $routingKey = getenv('RABBITMQ_ROUTING_KEY') ?: 'planning.session.list.request';

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

      $this->messenger()->addStatus($this->t('Session saved.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Could not send session to Planning.'));
    }
  }

}