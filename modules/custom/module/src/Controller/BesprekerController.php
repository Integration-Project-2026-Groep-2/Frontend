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
    $uid = $this->currentUser->id();
    $account = \Drupal\user\Entity\User::load($uid);
    $speakerUuid = $account ? $account->uuid() : NULL;

    $mijn_sessies = [];
    if ($speakerUuid) {
      try {
        $database = \Drupal::database();
        $query = $database->select('session_speaker', 'ss');
        $query->join('session', 's', 'ss.session_id = s.session_id');
        $query->leftJoin('location', 'l', 's.location_id = l.location_id');
        $query->fields('s', ['session_id', 'title', 'status', 'start_time']);
        $query->fields('l', ['room_name']);
        $query->condition('ss.speaker_id', $speakerUuid);
        $query->orderBy('s.date', 'ASC');
        $query->orderBy('s.start_time', 'ASC');
        $results = $query->execute()->fetchAll();

        foreach ($results as $result) {
          $status = strtoupper($result->status);
          $kleur = match(strtolower($result->status)) {
            'concept' => '#aaaaaa',
            'active' => '#00ff88',
            'cancelled' => '#ff4444',
            'full' => '#ffaa00',
            default => '#cccccc',
          };

          $mijn_sessies[] = [
            'session_id' => $result->session_id,
            'titel' => $result->title,
            'status' => $status,
            'locatie' => $result->room_name ?: 'Nog te bepalen',
            'tijd' => substr($result->start_time, 0, 5),
            'status_kleur' => $kleur,
          ];
        }
      } catch (\Exception $e) {
        \Drupal::logger('bespreker_controller')->error('Error fetching sessions: @err', ['@err' => $e->getMessage()]);
      }
    }

    return [
      '#theme' => 'bespreker_sessies',
      '#sessies' => $mijn_sessies,
      '#cache' => [
        'contexts' => ['user'],
      ],
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
  public function bezoekersDetails(string $sessionId): array {
    $database = \Drupal::database();
    
    // Fetch visitors
    $query = $database->select('registration', 'r');
    $query->leftJoin('frontend_user', 'u', 'r.user_id = u.user_id');
    $query->fields('u', ['first_name', 'last_name']);
    $query->addField('r', 'user_id', 'registration_user_id');
    $query->condition('r.session_id', $sessionId);
    $query->condition('r.is_active', 1);
    $results = $query->execute()->fetchAll();

    $bezoekers = [];
    foreach ($results as $row) {
      $firstName = $row->first_name;
      $lastName = $row->last_name;
      
      // If not in frontend_user table, try loading from Drupal
      if (empty($firstName) && empty($lastName)) {
        $registration_user_id = $row->registration_user_id; // from r.user_id
        
        // Try loading by UUID
        $drupal_users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['uuid' => $registration_user_id]);
        if (!$drupal_users) {
          // Try loading by CRM ID
          $drupal_users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['field_crm_id' => $registration_user_id]);
        }
        
        if ($drupal_users) {
          $account = reset($drupal_users);
          $firstName = $account->hasField('field_first_name') ? $account->get('field_first_name')->value : '';
          $lastName  = $account->hasField('field_surname')    ? $account->get('field_surname')->value    : '';
          if (empty($firstName) && empty($lastName)) {
            $firstName = $account->getDisplayName();
          }
        }
      }

      $bezoekers[] = [
        'naam' => trim($firstName . ' ' . $lastName) ?: 'Onbekende bezoeker',
        'type' => 'Bezoeker',
      ];
    }

    // Fetch session capacity
    $session = $database->select('session', 's')
      ->fields('s', ['capacity'])
      ->condition('session_id', $sessionId)
      ->execute()
      ->fetchAssoc();
    
    $max_capacity = $session ? (int) $session['capacity'] : 0;

    return [
      '#theme' => 'bespreker_bezoekers_details',
      '#bezoekers' => $bezoekers,
      '#totaal' => count($bezoekers),
      '#max_capaciteit' => $max_capacity,
    ];
  }

}
