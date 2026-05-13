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
  public function accountEdit() { return ['#theme' => 'bespreker_account_edit']; }
  /**
   * QR (Sub-onderdeel van Account)
   */
public function qr() { return ['#theme' => 'bespreker_qr']; }

  /**
   * BETALINGSGESCHIEDENIS
   */
public function betalingen() {
  return [
    '#theme' => 'bespreker_betalingen',
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

public function feedbackSummary() { return ['#theme' => 'bespreker_feedback_summary']; }

public function materialen() {
    return ['#theme' => 'bespreker_logistiek'];
  }

public function materialenGehuurd() { return ['#theme' => 'bespreker_materialen_gehuurd']; }

public function sessieDetails() { return ['#theme' => 'bespreker_sessie_details']; }
public function bezoekersDetails() { return ['#theme' => 'bespreker_bezoekers_details']; }  
}