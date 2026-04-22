<?php

namespace Drupal\shift_bezoeker\Controller;

use Drupal\Core\Controller\ControllerBase;

class BezoekerController extends ControllerBase {

  public function sessionsPage() {
    // We gebruiken nu hele uren voor de linker tijdsas-kolom
    $time_slots = [
      '10:00' => '10:00',
      '11:00' => '11:00',
      '12:00' => '12:00',
      '13:00' => '13:00',
      '14:00' => '14:00',
      '15:00' => '15:00',
      '16:00' => '16:00',
    ];

    $stages = [
      'main' => 'Main Stage',
      'zaal_a' => 'Zaal A',
      'zaal_b' => 'Zaal B',
    ];

    // Let op: de keys hier (zoals '10:00') moeten overeenkomen met de keys in $time_slots
    $grid_data = [
      'main' => [
        '13:00' => [
          'id' => 1, // Voeg deze ID's toe
          'title' => 'Future of Tech',
          'time' => '13:00 - 14:30',
          'type' => 'open'
        ]
      ],
      'zaal_a' => [
        '10:00' => [
          'id' => 2,
          'title' => 'AI & Design',
          'time' => '10:00 - 11:30',
          'type' => 'open'
        ]
      ],
      'zaal_b' => [
        '15:00' => [
          'id' => 3,
          'title' => 'Sustainability in Code',
          'time' => '15:30 - 17:00',
          'type' => 'registered' // Deze zal dus paars worden in je grid!
        ]
      ],
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
}