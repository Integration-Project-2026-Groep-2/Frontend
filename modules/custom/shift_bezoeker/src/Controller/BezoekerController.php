<?php

namespace Drupal\shift_bezoeker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;

class BezoekerController extends ControllerBase {

  public function sessionsPage() {
    // 1. Haal alle sessies op die Team Planning heeft aangemaakt
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
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

    $query = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('roles', 'bedrijf');

    $ids = $query->execute();

    $bedrijven = [];

    // Alleen proberen te laden als we daadwerkelijk ID's hebben gevonden
    if (!empty($ids)) {
      $users = \Drupal\user\Entity\User::loadMultiple($ids);

      foreach ($users as $user) {
        $bedrijven[] = [
          'naam' => $user->getDisplayName(),
          // Eventueel later: 'logo' => $user->get('field_logo')->entity->getFileUri(),
        ];
      }
    }

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
}