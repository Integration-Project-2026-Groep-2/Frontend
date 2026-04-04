<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;


class AdminController extends ControllerBase {
    
    public function page(): array {
        return [
            '#type' => 'markup',
            '#markup' => $this->t('Welcome to the Hello World Admin Page! 🛠️'),
        ];
    }

}