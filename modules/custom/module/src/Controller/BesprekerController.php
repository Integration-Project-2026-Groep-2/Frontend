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
        <div class="hero-section">
          <h1>Sprekers Dashboard</h1>
          <p>Welkom bij het Shift Festival. Beheer hier je sessies en gegevens.</p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 30px;">
          <div class="info-card">
            <h3>👤 Mijn Profiel</h3>
            <p>Bekijk je accountgegevens en persoonlijke QR-code.</p>
            <a href="/bespreker/account" class="btn-primary">Naar Account</a>
          </div>
          <div class="info-card">
            <h3>📅 Mijn Sessies</h3>
            <p>Check de status van je presentaties en bezoekersaantallen.</p>
            <a href="/bespreker/sessies" class="btn-primary">Naar Sessies</a>
          </div>
          <div class="info-card">
            <h3>💰 Betalingen</h3>
            <p>Overzicht van je financiële administratie.</p>
            <a href="/bespreker/betalingen" class="btn-primary">Bekijk Facturen</a>
          </div>
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
        <div class="info-card">
          <h2>Account gegevens</h2>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <p><strong>Naam:</strong> Bespreker Test</p>
              <p><strong>Email:</strong> bespreker@example.com</p>
              <p><a href="/bespreker/account/edit" class="btn-primary">Gegevens bewerken</a></p>
            </div>
            <div style="text-align: center; border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
              <p><strong>Jouw QR</strong></p>
              <a href="/bespreker/account/qr" style="text-decoration: none;">
                <div style="width: 100px; height: 100px; background: #333; color: white; display: flex; align-items: center; justify-content: center;">QR</div>
              </a>
            </div>
          </div>
        </div>
        <p><a href="/bespreker" style="color: #6a0dad;">« Terug naar Dashboard</a></p>',
    ];
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
  public function sessies(): array {
    return [
'#markup' => '
        <h2>Jouw Festival Sessies</h2>
        <div class="info-card" style="border-left: 5px solid #6a0dad;">
          <h3>Introductie tot Drupal 10</h3>
          <p><strong>Status:</strong> <span style="color: green;">On track</span></p>
          <p><strong>Zaal:</strong> Main Stage</p>
          <p><strong>Bezoekers:</strong> 124 ingeschreven</p>
        </div>
        <div class="info-card" style="border-left: 5px solid #ff4444; margin-top: 15px;">
          <h3>Docker Workshop</h3>
          <p><strong>Status:</strong> <span style="color: red;">⚠ 15 min vertraging</span></p>
          <p><strong>Zaal:</strong> Room B</p>
          <p><strong>Bezoekers:</strong> 45 ingeschreven</p>
        </div>
        <p><a href="/bespreker" style="color: #6a0dad; margin-top: 20px; display: block;">« Terug naar Dashboard</a></p>',
    ];
  }
}