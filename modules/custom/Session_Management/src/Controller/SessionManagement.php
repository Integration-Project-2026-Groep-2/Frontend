<?php

namespace Drupal\Session_Management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Session_Management\RabbitMQ\Message\SessionListRequest;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class SessionManagement extends ControllerBase {

  public function createPage() {
    try {
      $request = new SessionListRequest();
      $xml = $request->toXml();

      $host = getenv('RABBITMQ_HOST') ?: 'localhost';
      $port = (int) (getenv('RABBITMQ_PORT') ?: 5672);
      $user = getenv('RABBITMQ_USER') ?: 'guest';
      $pass = getenv('RABBITMQ_PASS') ?: 'guest';
      $exchange = getenv('RABBITMQ_EXCHANGE') ?: 'session.direct';
      $routingKey = getenv('RABBITMQ_ROUTING_KEY') ?: 'session.list.request';

      $connection = new AMQPStreamConnection($host, $port, $user, $pass);
      $channel = $connection->channel();

      $channel->exchange_declare($exchange, 'direct', false, true, false, false);

      $msg = new AMQPMessage($xml, [
        'content_type' => 'text/xml',
        'delivery_mode' => 2,
      ]);

      $channel->basic_publish($msg, $exchange, $routingKey);

      $channel->close();
      $connection->close();

      $this->messenger()->addStatus($this->t('Session list request sent.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Could not send session list request.'));
    }

    $create_url = Url::fromRoute('session_management.create');

    return [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h1>Sessions</h1>',
      ],
      'create_button' => [
        '#type' => 'link',
        '#title' => $this->t('Create new session'),
        '#url' => $create_url,
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['Title', 'Start', 'End', 'Location', 'Speaker', 'Capacity', 'Actions'],
        '#rows' => [],
        '#empty' => $this->t('No sessions loaded yet.'),
      ],
    ];
  }

}