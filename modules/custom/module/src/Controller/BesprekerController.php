<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BesprekerController extends ControllerBase {
/**
   * We gebruiken de create methode om services (zoals de huidige gebruiker)
   * netjes in de controller te injecteren volgens de Drupal-standaard.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * De huidige ingelogde gebruiker.
   */
  protected $currentUser;

  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }
  /**
   * SPREKERS (HOME)
   * De centrale hub volgens de sitemap.
   */
  public function home(): array {
    // Haal de echte weergavenaam op van de ingelogde gebruiker.
    $display_name = $this->currentUser->getDisplayName();
  return [
    '#theme' => 'bespreker_dashboard',
    '#user_name' => $display_name,
    // Dit zorgt dat de CSS van het thema echt geladen wordt!
    '#attached' => [
      'library' => [
        'shift_theme/global-styling', 
      ],
    ],
    // CRUCIAAL: Dit vertelt Drupal dat de inhoud van deze pagina
      // afhankelijk is van de ingelogde gebruiker ('user' context).
      '#cache' => [
        'contexts' => ['user'],
      ],
  ];
}

  /**
   * ACCOUNT GEGEVENS
   * Bevat links naar "Bewerken" en "QR" zoals in de sitemap.
   */
public function account() {
    return [
      '#theme' => 'bespreker_account',
      '#cache' => ['contexts' => ['user']],
    ];
  }

  /**
   * BEWERKEN ACCOUNT
   */
public function accountEdit() { 
    return [
      '#theme' => 'bespreker_account_edit',
      '#cache' => ['contexts' => ['user']],
    ]; 
  }  /**
   * QR (Sub-onderdeel van Account)
   */
public function qr() { return ['#theme' => 'bespreker_qr']; }

  /**
   * BETALINGSGESCHIEDENIS
   */
public function betalingen() {
    return [
      '#theme' => 'bespreker_betalingen',
      '#cache' => ['contexts' => ['user']],
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
