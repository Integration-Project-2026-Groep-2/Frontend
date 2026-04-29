<?php
<?php

namespace Drupal\Session_Management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class SessionCreate extends ControllerBase {

  public function createPage(): array {
    $back_url = Url::fromRoute('session_management.list');

    return [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h1>Create new session</h1>',
      ],
      'description' => [
        '#markup' => '<p>Session form will be added here later.</p>',
      ],
      'back_link' => [
        '#type' => 'link',
        '#title' => $this->t('Back to sessions'),
        '#url' => $back_url,
        '#attributes' => [
          'class' => ['button'],
        ],
      ],
    ];
  }

}