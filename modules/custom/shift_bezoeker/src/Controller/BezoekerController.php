<?php

namespace Drupal\shift_bezoeker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Drupal\shift_bezoeker\Form\EditAccountForm;

class BezoekerController extends ControllerBase {

  public function sessionsPage() {
    $db = \Drupal::database();

    // 1. Haal locaties op die minstens één sessie hebben
    $location_query = $db->select('location', 'l');
    $location_query->fields('l', ['location_id', 'room_name']);
    $location_query->join('session', 's', 's.location_id = l.location_id');
    $location_query->distinct();
    $location_query->orderBy('l.room_name', 'ASC');
    $locations = $location_query->execute()->fetchAll();

    if (empty($locations)) {
      return [
        '#markup' => '<div style="padding: 100px; text-align: center; color: white;">Geen sessies gepland op dit moment.</div>',
      ];
    }

    // 2. Haal alle sessies op
    $session_results = $db->select('session', 's')
      ->fields('s')
      ->orderBy('start_time', 'ASC')
      ->execute()
      ->fetchAll();

    // 3. Bepaal tijdspanne voor de grid
    $min_hour = 24;
    $max_hour = 0;

    foreach ($session_results as $s) {
      $start = (int) substr($s->start_time, 0, 2);
      $end = (int) substr($s->end_time, 0, 2);
      if ($start < $min_hour) $min_hour = $start;
      if ($end > $max_hour) $max_hour = $end;
    }

    // Buffer van 1 uur aan beide kanten
    $min_hour = max(0, $min_hour - 1);
    $max_hour = min(23, $max_hour + 1);

    // 4. Bouw tijdslots (elke 15 min)
    $interval = 15; // minuten
    $time_labels = [];
    for ($h = $min_hour; $h <= $max_hour; $h++) {
      $time_labels[] = sprintf('%02d:00', $h);
      $time_labels[] = sprintf('%02d:15', $h);
      $time_labels[] = sprintf('%02d:30', $h);
      $time_labels[] = sprintf('%02d:45', $h);
    }

    // 5. Formatteer sessies voor de grid
    $grid_sessions = [];
    foreach ($session_results as $s) {
      $start_parts = explode(':', $s->start_time);
      $end_parts = explode(':', $s->end_time);

      $start_m = (int)$start_parts[0] * 60 + (int)$start_parts[1];
      $end_m = (int)$end_parts[0] * 60 + (int)$end_parts[1];
      $grid_start_m = $min_hour * 60;

      // Bereken grid rij (Rij 1 = Header, dus +2)
      $row_start = (($start_m - $grid_start_m) / $interval) + 2;
      $row_span = ($end_m - $start_m) / $interval;

      // Zoek kolom index van locatie
      $col_index = 0;
      foreach ($locations as $idx => $loc) {
        if ($loc->location_id == $s->location_id) {
          $col_index = $idx + 2; // +1 voor tijdlabel kolom, +1 voor 1-based index
          break;
        }
      }

      if ($col_index > 0) {
        $grid_sessions[] = [
          'id' => $s->session_id,
          'title' => $s->title,
          'description' => $s->description,
          'time' => substr($s->start_time, 0, 5) . ' - ' . substr($s->end_time, 0, 5),
          'location' => '', // Wordt in template gezet of hier gezocht
          'row_start' => $row_start,
          'row_span' => $row_span,
          'col_index' => $col_index,
          'status' => $s->status,
        ];
      }
    }

    return [
      '#theme' => 'sessie_overzicht_template',
      '#current_date' => '22 April 2026',
      '#day_number' => '01',
      '#locations' => $locations,
      '#time_labels' => $time_labels,
      '#grid_sessions' => $grid_sessions,
      '#attached' => [
        'library' => [
          'shift_bezoeker/sessions',
        ],
      ],
    ];
  }

  public function accountPage() {
    return $this->formBuilder()->getForm(EditAccountForm::class);
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
        'library' => [
          'shift_theme/global-styling',
          'shift_theme/companies-page',
        ],
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