<?php

namespace Drupal\Session_Management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Session_Management\RabbitMQ\Message\SessionListRequest;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class SessionEditForm extends FormBase {

  public function __construct() {
    $this->setFormId('session_edit_form');
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
    $form['session_id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];
    $form['session_data'] = [
      '#type' => 'textarea',
      '#default_value' => '',
    ];
    return $form;
  }

}