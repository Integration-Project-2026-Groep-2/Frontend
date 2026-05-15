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
    $location_query->fields('l', ['location_id', 'room_name', 'address', 'capacity', 'status']);
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
    $uid = (int) $this->currentUser()->id();
    $account = \Drupal\user\Entity\User::load($uid);
    
    // Gebruik CRM ID als primary, fallback op Drupal UUID voor compatibiliteit/testing
    $user_id = NULL;
    if ($account) {
      $user_id = $account->hasField('field_crm_id') && !$account->get('field_crm_id')->isEmpty()
        ? $account->get('field_crm_id')->value
        : $account->uuid();
    }

    $active_registrations = [];
    if ($user_id) {
      $active_registrations = \Drupal::database()->select('registration', 'r')
        ->fields('r', ['session_id'])
        ->condition('user_id', $user_id)
        ->condition('is_active', 1)
        ->execute()
        ->fetchCol();
    }

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
          'registered' => in_array($s->session_id, $active_registrations),
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
      '#cache' => [
        'contexts' => ['user'],
      ],
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
    $uid = (int) $this->currentUser()->id();
    $account = \Drupal\user\Entity\User::load($uid);

    if (!$account) {
      $this->messenger()->addError($this->t('User not found.'));
      return $this->redirect('shift_bezoeker.sessions');
    }

    $db = \Drupal::database();

    // 1. Haal gegevens op (Gebruik CRM ID als userId voor Planning, fallback op Drupal UUID)
    $user_id = $account->hasField('field_crm_id') && !$account->get('field_crm_id')->isEmpty() 
      ? $account->get('field_crm_id')->value 
      : $account->uuid();

    \Drupal::logger('shift_bezoeker')->debug('Inschrijven started. Session: @session, User ID (CRM/UUID): @user', [
      '@session' => $session_id,
      '@user' => $user_id,
    ]);

    if (!$user_id) {
      $this->messenger()->addError($this->t('Geen geldig gebruikers-ID gevonden. Neem contact op met de beheerder.'));
      return $this->redirect('shift_bezoeker.sessions');
    }

    // 2. Controleer of de gebruiker al is ingeschreven (actief)
    $existing_active = $db->select('registration', 'r')
      ->fields('r', ['registration_id'])
      ->condition('session_id', $session_id)
      ->condition('user_id', $user_id)
      ->condition('is_active', 1)
      ->execute()
      ->fetchField();

    if ($existing_active) {
      $session_title = $db->select('session', 's')
        ->fields('s', ['title'])
        ->condition('session_id', $session_id)
        ->execute()
        ->fetchField() ?: $session_id;

      $this->messenger()->addWarning($this->t('Je bent al ingeschreven voor deze sessie.'));
      return [
        '#theme' => 'inschrijving_bevestigd',
        '#session_id' => $session_id,
        '#session_title' => $session_title,
      ];
    }

    // 4. Maak de inschrijving aan
    $registration_id = \Drupal::service('uuid')->generate();
    $timestamp = (new \DateTime())->format(\DateTime::ATOM);

    try {
      \Drupal::logger('shift_bezoeker')->debug('Inserting registration @id into database.', ['@id' => $registration_id]);
      $db->insert('registration')
        ->fields([
          'registration_id' => $registration_id,
          'session_id'      => $session_id,
          'user_id'         => $user_id,
          'registration_time' => date('Y-m-d H:i:s'),
          'is_active'       => 1,
        ])
        ->execute();
      \Drupal::logger('shift_bezoeker')->debug('Registration @id successfully inserted.', ['@id' => $registration_id]);
    }
    catch (\Exception $e) {
      \Drupal::logger('shift_bezoeker')->error('Failed to save registration: @err', ['@err' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Er ging iets mis bij het opslaan van je inschrijving.'));
      return $this->redirect('shift_bezoeker.sessions');
    }

    // 5. Stuur XML bericht naar RabbitMQ
    $message = new \Drupal\hello_world\RabbitMQ\Message\Planning\RegistrationCreatedMessage(
      registrationId: $registration_id,
      sessionId:      $session_id,
      userId:         $user_id,
      isActive:       TRUE,
      timestamp:      $timestamp,
    );

    $client = \Drupal\hello_world\RabbitMQ\RabbitMQClient::fromEnv();
    try {
      \Drupal::logger('shift_bezoeker')->debug('Attempting to publish RegistrationCreated message.');
      $client->publish($message);
      \Drupal::logger('shift_bezoeker')->info('RegistrationCreated message sent for session @session', ['@session' => $session_id]);
    }
    catch (\Exception $e) {
      \Drupal::logger('shift_bezoeker')->error('RabbitMQ publish failed: @err', ['@err' => $e->getMessage()]);
    }
    finally {
      $client->disconnect();
    }

    $session_title = $db->select('session', 's')
      ->fields('s', ['title'])
      ->condition('session_id', $session_id)
      ->execute()
      ->fetchField() ?: $session_id;

    return [
      '#theme' => 'inschrijving_bevestigd',
      '#session_id' => $session_id,
      '#session_title' => $session_title,
    ];
  }

  public function uitschrijven($session_id) {
    $uid = (int) $this->currentUser()->id();
    $account = \Drupal\user\Entity\User::load($uid);
    
    // Gebruik CRM ID als primary, fallback op Drupal UUID
    $user_id = NULL;
    if ($account) {
      $user_id = $account->hasField('field_crm_id') && !$account->get('field_crm_id')->isEmpty()
        ? $account->get('field_crm_id')->value
        : $account->uuid();
    }

    if (!$user_id) {
      $this->messenger()->addError($this->t('Gebruikers-ID niet gevonden.'));
      return $this->redirect('shift_bezoeker.sessions');
    }

    $db = \Drupal::database();

    // 1. Zoek de actieve inschrijving
    $registration = $db->select('registration', 'r')
      ->fields('r', ['registration_id'])
      ->condition('session_id', $session_id)
      ->condition('user_id', $user_id)
      ->condition('is_active', 1)
      ->execute()
      ->fetchAssoc();

    if (!$registration) {
      $this->messenger()->addWarning($this->t('Geen actieve inschrijving gevonden voor deze sessie.'));
      return $this->redirect('shift_bezoeker.sessions');
    }

    $registration_id = $registration['registration_id'];
    $timestamp = (new \DateTime())->format(\DateTime::ATOM);

    \Drupal::logger('shift_bezoeker')->debug('Uitschrijven started. Session: @session, Registration: @reg', [
      '@session' => $session_id,
      '@reg' => $registration_id,
    ]);

    // 2. Zet op inactief in DB
    try {
      $db->update('registration')
        ->fields(['is_active' => 0])
        ->condition('registration_id', $registration_id)
        ->execute();
      
      $this->messenger()->addStatus($this->t('Je bent succesvol uitgeschreven.'));
    }
    catch (\Exception $e) {
      \Drupal::logger('shift_bezoeker')->error('Failed to cancel registration: @err', ['@err' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Er ging iets mis bij het annuleren van je inschrijving.'));
      return $this->redirect('shift_bezoeker.sessions');
    }

    // 3. Stuur bericht naar RabbitMQ
    $message = new \Drupal\hello_world\RabbitMQ\Message\Planning\RegistrationCreatedMessage(
      registrationId: $registration_id,
      sessionId:      $session_id,
      userId:         $user_id,
      isActive:       FALSE,
      timestamp:      $timestamp,
    );

    $client = \Drupal\hello_world\RabbitMQ\RabbitMQClient::fromEnv();
    try {
      $client->publish($message);
    }
    catch (\Exception $e) {
      \Drupal::logger('shift_bezoeker')->error('RabbitMQ cancellation publish failed: @err', ['@err' => $e->getMessage()]);
    }
    finally {
      $client->disconnect();
    }

    return $this->redirect('shift_bezoeker.sessions');
  }

  public function homePage() {
    return [
      '#theme' => 'home_template',
    ];
  }

  public function accountVerwijderdPage() {
    return [
      '#theme' => 'account_verwijderd',
    ];
  }

  public function bedrijvenPage() {
    return [
      '#theme' => 'bedrijven_overzicht_template',
    ];
  }

}