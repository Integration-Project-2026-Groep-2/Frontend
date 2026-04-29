<?php
<?php

namespace Drupal\Session_Management\Controller;

use Drupal\Core\Controller\ControllerBase;

class SessionManagement extends ControllerBase {

  public function createPage() {
    return [
      '#theme' => 'session_create_page',
    ];
  }

}