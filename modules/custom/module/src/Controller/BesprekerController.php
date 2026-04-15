<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;

class BesprekerController extends ControllerBase {

  /**
   * De startpagina voor de bespreker.
   */
  public function home(): array {
    return [
      '#markup' => '
        <div style="padding: 20px; font-family: Arial, sans-serif;">
          <h1>Bespreker Dashboard</h1>
          <p>Welkom op je dashboard. Gebruik de links hieronder om te navigeren:</p>
          <ul>
            <li><a href="/bespreker/account">👤 Mijn Account gegevens</a></li>
            <li><a href="/bespreker/sessies">📅 Mijn Sessies</a></li>
            <li><a href="/bespreker/betalingen">💰 Betalingsgeschiedenis</a></li>
          </ul>
        </div>',
    ];
  }

  /**
   * Account gegevens pagina.
   */
  public function account(): array {
    return [
      '#markup' => '
        <div style="padding: 20px;">
          <h2>Mijn Account gegevens</h2>
          <p><strong>Naam:</strong> Bespreker Test</p>
          <p><strong>Email:</strong> bespreker@example.com</p>
          <a href="/bespreker/account/edit">Edit Account</a>
        </div>',
    ];
  }

  /**
   * Account bewerken pagina (deze ontbrak waarschijnlijk!).
   */
  public function accountEdit(): array {
    return [
      '#markup' => '<h2>Account Bewerken</h2><p>Hier komt later het formulier om je gegevens aan te passen.</p>',
    ];
  }

  /**
   * Sessies overzicht.
   */
  public function sessies(): array {
    return [
      '#markup' => '
        <div style="padding: 20px;">
          <h2>Mijn Sessies</h2>
          <ul>
            <li>Sessie 1: Introductie Drupal - <strong>Status: Actief</strong></li>
            <li>Sessie 2: Docker voor gevorderden - <strong>Status: In afwachting</strong></li>
          </ul>
        </div>',
    ];
  }

  /**
   * Betalingsgeschiedenis.
   */
  public function betalingen(): array {
    return [
      '#markup' => '<h2>Betalingsgeschiedenis</h2><p>Momenteel zijn er geen betalingen om weer te geven.</p>',
    ];
  }
}