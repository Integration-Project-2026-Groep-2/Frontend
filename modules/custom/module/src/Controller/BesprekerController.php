<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;

class BesprekerController extends ControllerBase {

  /**
   * SPREKERS (HOME)
   * De centrale hub volgens de sitemap.
   */
  public function home(): array {
    return [
      '#markup' => '
        <div style="padding: 20px; font-family: sans-serif;">
          <h1>Sprekers Dashboard</h1>
          <p>Welkom op je dashboard. Navigeer naar de verschillende onderdelen:</p>
          <ul>
            <li><a href="/bespreker/account">👤 Account gegevens</a></li>
            <li><a href="/bespreker/betalingen">💰 Betalingsgeschiedenis</a></li>
            <li><a href="/bespreker/sessies">📅 Sessies</a></li>
          </ul>
        </div>',
    ];
  }

  /**
   * ACCOUNT GEGEVENS
   * Bevat links naar "Bewerken" en "QR" zoals in de sitemap.
   */
  public function account(): array {
    return [
      '#markup' => '
        <div style="padding: 20px; font-family: sans-serif;">
          <h2>Account gegevens</h2>
          <p><strong>Naam:</strong> Bespreker Test</p>
          <p><strong>Email:</strong> bespreker@example.com</p>
          <hr>
          <p><a href="/bespreker/account/edit">➡️ Bewerk Account</a></p>
          <p><a href="/bespreker/account/qr">➡️ Mijn QR-code bekijken</a></p>
          <br><a href="/bespreker">« Terug naar Home</a>
        </div>',
    ];
  }

  /**
   * BEWERKEN ACCOUNT
   */
  public function accountEdit(): array {
    return [
      '#markup' => '
        <div style="padding: 20px; font-family: sans-serif;">
          <h2>Bewerk je gegevens</h2>
          <form>
            <label>Naam:</label><br><input type="text" value="Bespreker Test"><br><br>
            <button type="submit">Opslaan</button>
          </form>
          <br><a href="/bespreker/account">« Terug</a>
        </div>',
    ];
  }

  /**
   * QR (Sub-onderdeel van Account)
   */
  public function qr(): array {
    return [
      '#markup' => '
        <div style="padding: 20px; text-align: center; font-family: sans-serif;">
          <h2>Jouw Persoonlijke QR-code</h2>
          <div style="width: 200px; height: 200px; background: #333; color: #fff; display: flex; align-items: center; justify-content: center; margin: auto;">
            [QR CODE SCANNER]
          </div>
          <br><a href="/bespreker/account">« Terug</a>
        </div>',
    ];
  }

  /**
   * BETALINGSGESCHIEDENIS
   */
  public function betalingen(): array {
    return [
      '#markup' => '
        <div style="padding: 20px; font-family: sans-serif;">
          <h2>Betalingsgeschiedenis</h2>
          <table border="1" style="width: 100%; border-collapse: collapse;">
            <tr style="background: #eee;"><th>Datum</th><th>Bedrag</th><th>Status</th></tr>
            <tr><td>15/04/2026</td><td>€ 150,00</td><td>Betaald</td></tr>
          </table>
          <br><a href="/bespreker">« Terug naar Home</a>
        </div>',
    ];
  }

  /**
   * SESSIES
   * Inclusief "Status sessie" en "Aantal bezoekers" zoals in sitemap.
   */
  public function sessies(): array {
    return [
      '#markup' => '
        <div style="padding: 20px; font-family: sans-serif;">
          <h2>Mijn Sessies</h2>
          <div style="border: 1px solid #ccc; padding: 15px; margin-bottom: 15px;">
            <h3>Sessie: Drupal & Docker</h3>
            <p><strong>Status sessie:</strong> <span style="color: green;">On track</span> (Geen vertragingen)</p>
            <p><strong>Aantal ingeschreven bezoekers:</strong> 42</p>
          </div>
          <br><a href="/bespreker">« Terug naar Home</a>
        </div>',
    ];
  }
}