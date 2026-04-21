<?php

namespace Drupal\shift_bezoeker\Controller;

use Drupal\Core\Controller\ControllerBase;

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
      '#user' => $user_data,
      '#attached' => [
        'library' => ['shift_theme/global-styling'],
      ],
    ];
  }

}