<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;

class BesprekerController extends ControllerBase {

  public function home(): array {
    return [
      '#markup' => '
        <div style="padding: 20px; font-family: sans-serif;">
          <h1>Welkom op het Bespreker Dashboard</h1>
          <p>Beheer hier je sessies en bekijk registraties.</p>
          <div style="margin-top: 20px;">
             <a href="/register_visitor" style="padding: 10px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">Bezoekers Registreren</a>
          </div>
        </div>
      ',
    ];
  }
}