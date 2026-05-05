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
          <div class="info-card"><h3>📦 Materialen</h3><p>Huur en logistiek</p><a href="/bespreker/materialen" class="btn-primary">Bekijk</a></div>
          <div class="info-card"><h3>💬 Feedback</h3><p>Wat vonden de bezoekers?</p><a href="/bespreker/feedback" class="btn-primary">Bekijk</a></div>
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
        <div class="info-card">
          <h3>Drupal 10 Deep Dive</h3>
          <p>Status: <strong>On track</strong></p>
          <a href="/bespreker/sessies/details" class="btn-primary">Sessie details</a>
          <a href="/bespreker/sessies/bezoekers" class="btn-primary" style="background:#444;">Bezoekers details</a>
        </div>
        <p><a href="/bespreker" style="color: #6a0dad;">« Terug</a></p>',
    ];
  }
  public function feedback(): array {
    return [
      '#markup' => '
        <div class="info-card">
          <h2>Feedback Overzicht</h2>
          <p>Gemiddelde score: ⭐ 4.8/5</p>
          <a href="/bespreker/feedback/summary" class="btn-primary">Summary en Analyse</a>
        </div>
        <p><a href="/bespreker" style="color: #6a0dad;">« Terug</a></p>',
    ];
  }

  public function feedbackSummary(): array {
    return ['#markup' => '<div class="info-card"><h2>Analyse van Feedback</h2><p>Gedetailleerde grafieken en opmerkingen van bezoekers.</p></div>'];
  }

  public function materialen(): array {
    return [
      '#markup' => '
        <div class="info-card">
          <h2>Logistiek & Materialen</h2>
          <p>Beheer de benodigdheden voor je sessie.</p>
          <a href="/bespreker/materialen/gehuurd" class="btn-primary">Gehuurde materialen</a>
        </div>
        <p><a href="/bespreker" style="color: #6a0dad;">« Terug</a></p>',
    ];
  }

  public function materialenGehuurd(): array {
    return ['#markup' => '<div class="info-card"><h2>Mijn Gehuurde Materialen</h2><ul><li>Beamerset (Gereserveerd)</li><li>Draadloze microfoon (Gereserveerd)</li></ul></div>'];
  }

  public function sessieDetails(): array { return ['#markup' => '<div class="info-card"><h2>Sessie Details</h2><p>Informatie over vertragingen, zaalwijzigingen en annulaties.</p></div>']; }
  public function bezoekersDetails(): array { return ['#markup' => '<div class="info-card"><h2>Bezoekers Details</h2><p>Lijst met ingeschreven deelnemers en hun profielen.</p></div>']; }
  
}