<?php

namespace Drupal\Session_Management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class SessionEdit extends ControllerBase {

  public function editPage(string $id): array {
    $back_url = Url::fromRoute('session_management.list');

    return [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h1>Edit session</h1>',
      ],
      'description' => [
        '#markup' => '<p>Edit page for session ID: ' . $id . '</p>',
      ],
    
      'form' => \Drupal::formBuilder()->getForm(SessionEditForm::class, $id),

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