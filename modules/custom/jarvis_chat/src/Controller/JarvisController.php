<?php

namespace Drupal\jarvis_chat\Controller;

use Drupal\Core\Controller\ControllerBase;

class JarvisController extends ControllerBase {

  public function page(): array {
    return ['#markup' => 'Jarvis'];
  }

}
