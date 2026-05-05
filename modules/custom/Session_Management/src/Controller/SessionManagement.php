<?php

namespace Drupal\Session_Management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class SessionManagement extends ControllerBase {

  public function content() {
    // TODO: In the future, fetch all sessions from Planning via RabbitMQ
    // For now, show placeholder message
    $this->messenger()->addStatus($this->t('Session list will be loaded from Planning.'));

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