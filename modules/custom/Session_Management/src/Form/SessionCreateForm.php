<?php

namespace Drupal\session_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hello_world\RabbitMQ\Message\Planning\PlanningSessionCreatedMessage;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating a new session.
 */
class SessionCreateForm extends FormBase {

  protected $messenger;

  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  public function getFormId(): string {
    return 'session_create_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Laad de dialog library voor de modal.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $form['title'] = [
      '#type'      => 'textfield',
      '#title'     => $this->t('Title'),
      '#required'  => TRUE,
      '#maxlength' => 255,
    ];

    $form['date'] = [
      '#type'     => 'date',
      '#title'    => $this->t('Date'),
      '#required' => TRUE,
    ];

    $form['startTime'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Start Time'),
      '#required'      => TRUE,
      '#default_value' => '12:00',
      '#attributes'    => ['placeholder' => 'HH:mm'],
    ];

    $form['endTime'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('End Time'),
      '#required'      => TRUE,
      '#default_value' => '13:00',
      '#attributes'    => ['placeholder' => 'HH:mm'],
    ];

    $form['location_wrapper'] = [
      '#type'  => 'container',
      '#attributes' => ['class' => ['location-wrapper']],
    ];

    $form['location_wrapper']['location'] = [
      '#type'         => 'select',
      '#title'        => $this->t('Location'),
      '#options'      => $this->getLocationOptions(),
      '#empty_option' => $this->t('- Select -'),
      '#required'     => TRUE,
    ];

    // "Nieuwe locatie" knop opent een modal via AJAX.
    $form['location_wrapper']['new_location_button'] = [
      '#type'       => 'link',
      '#title'      => $this->t('+ New location'),
      '#url'        => Url::fromRoute('session_management.location.create.modal'),
      '#attributes' => [
        'class'               => ['button', 'button--secondary', 'use-ajax'],
        'data-dialog-type'    => 'modal',
        'data-dialog-options' => '{"width": 600}',
      ],
    ];

    $form['speaker'] = [
      '#type'         => 'select',
      '#title'        => $this->t('Speaker'),
      '#options'      => $this->getSpeakerOptions(),
      '#empty_option' => $this->t('- Select -'),
      '#required'     => FALSE,
      '#description'  => $this->t('Optional — only users with the Speaker role are shown.'),
    ];

    $form['status'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Status'),
      '#options'       => [
        'concept'   => $this->t('Concept'),
        'active'    => $this->t('Active'),
        'cancelled' => $this->t('Cancelled'),
        'full'      => $this->t('Full'),
      ],
      '#required'      => TRUE,
      '#default_value' => 'concept',
    ];

    $form['capacity'] = [
      '#type'        => 'number',
      '#title'       => $this->t('Capacity'),
      '#required'    => TRUE,
      '#min'         => 1,
      '#description' => $this->t('Maximum number of participants.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Create Session'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Returns hardcoded location options.
   * TODO: replace with data from Planning via RabbitMQ.
   *
   * @return array<string, string>
   */
  protected function getLocationOptions(): array {
    $options = [];
    try {
      $results = \Drupal::database()->select('location', 'l')
        ->fields('l', ['location_id', 'room_name'])
        ->orderBy('room_name', 'ASC')
        ->execute()
        ->fetchAll();

      foreach ($results as $location) {
        $options[$location->location_id] = $location->room_name;
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('session_management')->error('Failed to load location options: @err', ['@err' => $e->getMessage()]);
    }
    return $options;
  }

  /**
   * Laadt alle actieve Drupal users met de rol 'speaker'.
   *
   * @return array<int, string>
   */
  protected function getSpeakerOptions(): array {
    $storage  = \Drupal::entityTypeManager()->getStorage('user');
    $accounts = $storage->loadByProperties(['status' => 1]);

    $options = [];
    foreach ($accounts as $account) {
      if ($account->hasRole('speaker')) {
        $firstName = $account->hasField('field_first_name') ? $account->get('field_first_name')->value : '';
        $lastName  = $account->hasField('field_surname')    ? $account->get('field_surname')->value    : '';
        $label     = trim($firstName . ' ' . $lastName) ?: $account->getEmail();
        $options[$account->id()] = $label;
      }
    }

    return $options;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $startTime = $this->extractTime($form_state->getValue('startTime'));
    $endTime   = $this->extractTime($form_state->getValue('endTime'));

    if (!$startTime) {
      $raw = var_export($form_state->getValue('startTime'), TRUE);
      $form_state->setErrorByName('startTime', $this->t('Start time is required (Received: @val).', ['@val' => $raw]));
    }
    if (!$endTime) {
      $raw = var_export($form_state->getValue('endTime'), TRUE);
      $form_state->setErrorByName('endTime', $this->t('End time is required (Received: @val).', ['@val' => $raw]));
    }

    if ($startTime && $endTime && $startTime >= $endTime) {
      $form_state->setErrorByName('endTime', $this->t('End time must be after start time.'));
    }
  }

  /**
   * Helper to extract time string from Drupal form value (handles string or array).
   */
  private function extractTime($value): ?string {
    if (is_string($value) && !empty($value)) {
      return $value;
    }
    if (is_array($value)) {
      if (isset($value['time']) && is_string($value['time'])) {
        return $value['time'];
      }
      if (isset($value['hour'], $value['minute'])) {
        return sprintf('%02d:%02d', $value['hour'], $value['minute']);
      }
    }
    return NULL;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger->addStatus($this->t('Processing session creation...'));
    $date      = $form_state->getValue('date');
    $startTime = $this->extractTime($form_state->getValue('startTime'));
    if ($startTime && strlen($startTime) === 5) {
      $startTime .= ':00';
    }
    $endTime = $this->extractTime($form_state->getValue('endTime'));
    if ($endTime && strlen($endTime) === 5) {
      $endTime .= ':00';
    }

    $speakerId = $form_state->getValue('speaker') ?: NULL;
    $speakerUuid = NULL;
    if ($speakerId) {
      $speaker = \Drupal::entityTypeManager()->getStorage('user')->load($speakerId);
      if ($speaker) {
        $speakerUuid = $speaker->uuid();
      }
    }

    $locationId = $form_state->getValue('location') ?: NULL;
    $locationOptions = $this->getLocationOptions();
    $locationLabel = $locationId && isset($locationOptions[$locationId]) ? $locationOptions[$locationId] : NULL;

    $sessionUuid = \Drupal::service('uuid')->generate();
    $title = $form_state->getValue('title');

    try {
      \Drupal::database()->insert('session')
        ->fields([
          'session_id'   => $sessionUuid,
          'title'        => $title,
          'date'         => $date,
          'start_time'   => $startTime,
          'end_time'     => $endTime,
          'location_id'  => $locationId,
          'capacity'     => (int) $form_state->getValue('capacity'),
          'status'       => $form_state->getValue('status'),
          'sync_status'  => 'pending',
        ])
        ->execute();
      
      $this->messenger->addStatus($this->t('Session "@title" saved to database.', ['@title' => $title]));
    }
    catch (\Exception $e) {
      \Drupal::logger('session_management')->error('Failed to save session to DB: @err', ['@err' => $e->getMessage()]);
      $this->messenger->addError($this->t('Failed to save session locally.'));
    }

    $message = new PlanningSessionCreatedMessage(
      title:      $title,
      date:       $date,
      startTime:  $startTime,
      endTime:    $endTime,
      capacity:   (int) $form_state->getValue('capacity'),
      locationId: $locationId,
      location:   $locationLabel,
      speakerId:  $speakerUuid,
      status:     $form_state->getValue('status'),
      timestamp:  (new \DateTime())->format(\DateTime::ATOM),
    );

    $client = RabbitMQClient::fromEnv();
    try {
      $client->publish($message);
      $this->messenger->addStatus($this->t('Session "@title" sent to planning.', [
        '@title' => $title,
      ]));
      $form_state->setRedirect('session_management.list');
    }
    catch (\RuntimeException $e) {
      \Drupal::logger('session_management')->error(
        'RabbitMQ session publish failed: @err', ['@err' => $e->getMessage()]
      );
      $this->messenger->addError($this->t('Session could not be sent to planning. Please try again.'));
    }
    finally {
      $client->disconnect();
    }
  }

}