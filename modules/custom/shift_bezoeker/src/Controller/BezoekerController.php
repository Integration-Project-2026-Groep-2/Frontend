<?php

namespace Drupal\shift_bezoeker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;

class BezoekerController extends ControllerBase {

  /**
   * Pagina voor het sessie overzicht.
   */
  public function sessionsPage() {
    $open_sessions = [
      ['sessionName' => 'AI & Design', 'startTime' => '10:00', 'location' => 'Zaal A'],
      ['sessionName' => 'Future of Tech', 'startTime' => '13:00', 'location' => 'Main Stage'],
    ];

    $my_sessions = [
      ['sessionName' => 'Sustainability in Code', 'startTime' => '15:30', 'location' => 'Zaal B'],
    ];

    return [
      '#theme' => 'sessie_overzicht_template',
      '#open_sessions' => $open_sessions,
      '#my_sessions' => $my_sessions,
      '#attached' => [
        'library' => ['shift_theme/global-styling'],
      ],
    ];
  }

  /**
   * Pagina voor de accountgegevens.
   */
  public function accountPage() {
    $user_data = [
      'firstName' => 'Liam',
      'lastName' => 'Stammeleer',
      'email' => 'liam.stammeleer@example.com',
      'phone' => '+32 470 00 00 00',
      'company' => 'Erasmus',
      'role' => 'Bezoeker',
    ];

    return [
      '#theme' => 'account_gegevens_template',
      '#user' => [
        'firstName' => 'Bezoeker',
        'lastName' => 'Naam',
        'email' => 'test@shift.be',
        'company' => 'Shift Festival',
      ],
    ];
  }
  public function inschrijven($session_id) {
    return [
      '#theme' => 'inschrijving_bevestigd',
      '#session_id' => $session_id,
    ];
  }

  public function uitschrijven($session_id) {
    return $this->redirect('shift_bezoeker.sessions');
  }

  public function homePage() {
    return [
      '#theme' => 'home_template',
    ];
  }

  public function bedrijvenPage() {
    $bedrijven = $this->getBedrijven();

    return [
      '#theme' => 'bezoeker_bedrijven',
      '#bedrijven' => $bedrijven,
      '#attached' => [
        'library' => ['shift_theme/global-styling'],
      ],
    ];
  }

  /**
   * Helper to load groups of type 'company' (optimized: load files in bulk).
   */
  private function getBedrijven() {
    $query = \Drupal::entityQuery('group')
      ->accessCheck(false)// zorgt dat ook niet ingelogde gebruikers de bedrijven kunnen zien op de onze partners pagina
      ->condition('type', 'company');//checked op groepeen met type company

    $gids = $query->execute();
    $groepen = \Drupal::entityTypeManager()->getStorage('group')->loadMultiple($gids);

    // Verzamel alle fids eerst.
    $fids = [];
    foreach ($groepen as $groep) {
      if ($groep->hasField('field_logo')) {
        $field = $groep->get('field_logo');
        if (!$field->isEmpty()) {
          $fid = $field->target_id ?? NULL;
          if ($fid) {
            $fids[$fid] = $fid;
          }
        }
      }
    }

    // Laad bestanden in één keer.
    $files = [];
    if (!empty($fids)) {
      $files = File::loadMultiple($fids);
    }

    $result = [];
    $url_generator = \Drupal::service('file_url_generator');

    foreach ($groepen as $groep) {
      $naam = $groep->label();

      $beschrijving = '';
      if ($groep->hasField('field_description')) {
        $desc_field = $groep->get('field_description');
        if (!$desc_field->isEmpty()) {
          $beschrijving = $desc_field->value ?? '';
        }
      }

      $logo_url = NULL;
      if ($groep->hasField('field_logo')) {
        $logo_field = $groep->get('field_logo');
        if (!$logo_field->isEmpty()) {
          $fid = $logo_field->target_id ?? NULL;
          if ($fid && isset($files[$fid])) {
            $file = $files[$fid];
            $logo_url = $url_generator->generateAbsoluteString($file->getFileUri());
          }
        }
      }

      $result[] = [
        'naam' => $naam,
        'beschrijving' => $beschrijving,
        'logo' => $logo_url,
      ];
    }

    return $result;
  }
}