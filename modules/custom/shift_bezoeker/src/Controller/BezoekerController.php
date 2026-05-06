<?php

namespace Drupal\shift_bezoeker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;

class BezoekerController extends ControllerBase {

  public function sessionsPage() {
    // 1. Haal alle sessies op die Team Planning heeft aangemaakt
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'session'); // Check of ze het 'session' of 'sessie' hebben genoemd!

    $nids = $query->execute();
    $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);

    $grid_data = [];

    // 2. Loop door de database resultaten
    foreach ($nodes as $node) {
      // Haal de waarden op uit de velden van Team Planning
      // Let op: vervang 'field_...' door de echte machine-namen van hun velden!
      $start_time = $node->get('field_start_time')->value; // Bijv: '10:00'
      $end_time = $node->get('field_end_time')->value;     // Bijv: '11:30'
      $stage_key = $node->get('field_stage')->value;      // Bijv: 'main', 'zaal_a', 'zaal_b'

      // Vul de grid_data array dynamisch
      $grid_data[$stage_key][$start_time] = [
        'id'    => $node->id(),
        'title' => $node->getTitle(),
        'time'  => $start_time . ' - ' . $end_time,
        'type'  => 'open', // Dit kun je later linken aan inschrijvingen
      ];
    }

    // De definities van je grid blijven hier staan
    $time_slots = [
      '10:00' => '10:00', '11:00' => '11:00', '12:00' => '12:00',
      '13:00' => '13:00', '14:00' => '14:00', '15:00' => '15:00', '16:00' => '16:00',
    ];

    $stages = [
      'main' => 'Main Stage',
      'zaal_a' => 'Zaal A',
      'zaal_b' => 'Zaal B',
    ];

    return [
      '#theme' => 'sessie_overzicht_template',
      '#current_date' => '22 April 2026',
      '#day_number' => '01',
      '#time_slots' => $time_slots,
      '#stages' => $stages,
      '#grid_data' => $grid_data,
    ];
  }

  public function accountPage() {
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
        'library' => [
          'shift_theme/global-styling',
          'shift_theme/companies-page',
        ],
      ],
    ];
  }

  /**
   * Helper to load groups of type 'company'.
   */
  private function getBedrijven() {
    $groepen = \Drupal::entityTypeManager()->getStorage('group')->loadByProperties(['type' => 'company_name']);
    $result = [];

    \Drupal::logger('shift_bezoeker')->notice('Found @count groups for type company_name', [
      '@count' => count($groepen),
    ]);

    foreach ($groepen as $groep) {
      $naam = $groep->label();
      \Drupal::logger('shift_bezoeker')->notice('Processing group: @naam (ID: @id)', [
        '@naam' => $naam,
        '@id' => $groep->id(),
      ]);

      $beschrijving = '';
      if ($groep->hasField('field_description')) {
        $desc_field = $groep->get('field_description');
        \Drupal::logger('shift_bezoeker')->notice('field_description exists. empty=@empty', [
          '@empty' => $desc_field->isEmpty() ? 'yes' : 'no',
        ]);

        if (!$desc_field->isEmpty()) {
          $beschrijving = $desc_field->value ?? '';
          \Drupal::logger('shift_bezoeker')->notice('Description value: @desc', [
            '@desc' => $beschrijving,
          ]);
        }
      }
      else {
        \Drupal::logger('shift_bezoeker')->error('field_description missing on group @id', [
          '@id' => $groep->id(),
        ]);
      }

      $logo_url = NULL;
      if ($groep->hasField('field_logo')) {
        $logo_field = $groep->get('field_logo');
        \Drupal::logger('shift_bezoeker')->notice('field_logo exists. empty=@empty', [
          '@empty' => $logo_field->isEmpty() ? 'yes' : 'no',
        ]);

        if (!$logo_field->isEmpty()) {
          $fid = $logo_field->target_id ?? NULL;
          \Drupal::logger('shift_bezoeker')->notice('Logo fid: @fid', [
            '@fid' => $fid ?: 'none',
          ]);

          if ($fid) {
            $file = File::load($fid);
            if ($file) {
              $logo_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
              \Drupal::logger('shift_bezoeker')->notice('Logo URL built: @url', [
                '@url' => $logo_url,
              ]);
            }
            else {
              \Drupal::logger('shift_bezoeker')->error('File load failed for fid @fid', [
                '@fid' => $fid,
              ]);
            }
          }
        }
      }
      else {
        \Drupal::logger('shift_bezoeker')->error('field_logo missing on group @id', [
          '@id' => $groep->id(),
        ]);
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