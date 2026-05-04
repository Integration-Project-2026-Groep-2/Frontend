<?php

namespace Drupal\Session_Management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Session_Management\Form\SessionEditForm;

class SessionEdit extends ControllerBase {

  public function editPage(string $id): array {
    return [
      'form' => \Drupal::formBuilder()->getForm(SessionEditForm::class, $id),
    ];
  }

}