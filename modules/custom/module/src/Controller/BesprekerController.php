<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller voor de sprekers-sectie van het Shift Festival.
 */
class BesprekerController extends ControllerBase {

  /**
   * De huidige ingelogde gebruiker.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * BesprekerController constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * De huidige gebruiker service.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * SPREKERS (HOME) - De centrale hub.
   *
   * @return array
   * Een render array voor het dashboard.
   */
  public function home(): array {
    return [
      '#theme' => 'bespreker_dashboard',
      '#user_name' => $this->currentUser->getDisplayName(),
      '#attached' => [
        'library' => [
          'shift_theme/global-styling',
        ],
      ],
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Toont de account hoofdpagina.
   */
public function account(): array {
    return [
      '#theme' => 'bespreker_account',
      '#user_name' => $this->currentUser->getDisplayName(),
      '#user_email' => $this->currentUser->getEmail(),
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Pagina om accountgegevens te bewerken.
   */
  public function accountEdit(): array {
    return [
      '#theme' => 'bespreker_account_edit',
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Toont de persoonlijke QR-code voor toegang.
   */
  public function qr(): array {
    return [
      '#theme' => 'bespreker_qr',
    ];
  }

  /**
   * Overzicht van betalingen en onkosten.
   */
  public function betalingen(): array {
    return [
      '#theme' => 'bespreker_betalingen',
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Overzicht van alle sessies van de spreker.
   */
  public function sessies(): array {
    return [
      '#theme' => 'bespreker_sessies',
    ];
  }

  /**
   * Algemene feedback pagina.
   */
  public function feedback(): array {
    return [
      '#theme' => 'bespreker_feedback',
    ];
  }

  /**
   * Gedetailleerde analyse van de feedback.
   */
  public function feedbackSummary(): array {
    return [
      '#theme' => 'bespreker_feedback_summary',
    ];
  }

  /**
   * Overzicht van logistieke zaken.
   */
  public function materialen(): array {
    return [
      '#theme' => 'bespreker_logistiek',
    ];
  }

  /**
   * Details van gehuurde materialen.
   */
  public function materialenGehuurd(): array {
    return [
      '#theme' => 'bespreker_materialen_gehuurd',
    ];
  }

  /**
   * Specifieke details per sessie.
   */
  public function sessieDetails(): array {
    return [
      '#theme' => 'bespreker_sessie_details',
    ];
  }

  /**
   * Lijst met bezoekers voor de sessie.
   */
  public function bezoekersDetails(): array {
    return [
      '#theme' => 'bespreker_bezoekers_details',
    ];
  }

}
