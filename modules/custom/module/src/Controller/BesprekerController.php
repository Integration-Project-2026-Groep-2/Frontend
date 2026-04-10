<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;

class BesprekerController extends ControllerBase {

  public function home(): array {
    return [
      '#markup' => '
        <div style="max-width:1000px; margin:auto; font-family:Arial;">
          <h1>Bespreker Dashboard</h1>
          <p>Welkom op de bespreker homepage.</p>

          <h2>Acties</h2>
          <ul>
            <li>Registraties bekijken</li>
            <li>Sessies beheren</li>
            <li>Deelnemers beheren</li>
          </ul>

          <h2>Status</h2>
          <p>Frontend in ontwikkeling...</p>
        </div>
      ',
    ];
  }
}