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
    '#theme' => 'bespreker_dashboard',
    '#user_name' => 'Bespreker Test',
    // Dit zorgt dat de CSS van het thema echt geladen wordt!
    '#attached' => [
      'library' => [
        'shift_theme/global-styling', 
      ],
    ],
  ];
}

  /**
   * ACCOUNT GEGEVENS
   * Bevat links naar "Bewerken" en "QR" zoals in de sitemap.
   */
public function account() {
    return ['#theme' => 'bespreker_account'];
  }

  /**
   * BEWERKEN ACCOUNT
   */
  public function accountEdit(): array {
    return [
      '#markup' => '
        <div class="info-card">
          <h2>Bewerk Profiel</h2>
          <form class="shift-festival-form">
            <div class="form-row" style="display: flex; gap: 20px; margin-bottom: 15px;">
              <div style="flex: 1;">
                <label>Voornaam</label><br>
                <input type="text" value="Bespreker" style="width: 100%; padding: 8px;">
              </div>
              <div style="flex: 1;">
                <label>Achternaam</label><br>
                <input type="text" value="Test" style="width: 100%; padding: 8px;">
              </div>
            </div>
            <div style="margin-bottom: 15px;">
              <label>E-mailadres</label><br>
              <input type="email" value="bespreker@example.com" style="width: 100%; padding: 8px;">
            </div>
            <button type="submit" class="btn-primary">Wijzigingen Opslaan</button>
          </form>
        </div>
        <p><a href="/bespreker/account" style="color: #6a0dad;">« Annuleren</a></p>',];
  }

  /**
   * QR (Sub-onderdeel van Account)
   */
  public function qr(): array {
    return [
      '#markup' => '<div class="info-card"><h2>QR Scanner</h2><p>Toon deze code bij de ingang.</p></div>'
    ];
  }

  /**
   * BETALINGSGESCHIEDENIS
   */
  public function betalingen(): array {
    return [
'#markup' => '<div class="info-card"><h2>Betalingen</h2><p>Geen openstaande facturen.</p></div>'
    ];
  }

  /**
   * SESSIES
   * Inclusief "Status sessie" en "Aantal bezoekers" zoals in sitemap.
   */
public function sessies() {
    return ['#theme' => 'bespreker_sessies'];
  }

public function feedback() {
    return ['#theme' => 'bespreker_feedback'];
  }

  public function feedbackSummary(): array {
    return ['#markup' => '<div class="info-card"><h2>Analyse van Feedback</h2><p>Gedetailleerde grafieken en opmerkingen van bezoekers.</p></div>'];
  }

public function materialen() {
    return ['#theme' => 'bespreker_logistiek'];
  }

  public function materialenGehuurd(): array {
    return ['#markup' => '<div class="info-card"><h2>Mijn Gehuurde Materialen</h2><ul><li>Beamerset (Gereserveerd)</li><li>Draadloze microfoon (Gereserveerd)</li></ul></div>'];
  }

  public function sessieDetails(): array { return ['#markup' => '<div class="info-card"><h2>Sessie Details</h2><p>Informatie over vertragingen, zaalwijzigingen en annulaties.</p></div>']; }
  public function bezoekersDetails(): array { return ['#markup' => '<div class="info-card"><h2>Bezoekers Details</h2><p>Lijst met ingeschreven deelnemers en hun profielen.</p></div>']; }
  
}