<?php

namespace Drupal\session_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\hello_world\RabbitMQ\Message\Planning\PlanningSessionUpdatedMessage;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Form for editing an existing session.
 */
class SessionEditForm extends FormBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'session_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sessionId = NULL): array {
    $sessionData = [];
    if ($sessionId) {
      try {
        $sessionData = (array) \Drupal::database()->select('session', 's')
          ->fields('s')
          ->condition('session_id', $sessionId)
          ->execute()
          ->fetchAssoc();
      }
      catch (\Exception $e) {
        $this->messenger->addError($this->t('Could not load session data.'));
      }
    }

    if (empty($sessionData)) {
      $this->messenger->addError($this->t('Session not found.'));
      return ['#markup' => $this->t('Session not found.')];
    }

    $form['sessionId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Session ID'),
      '#default_value' => $sessionData['session_id'],
      '#disabled' => TRUE,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $sessionData['title'],
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#required' => TRUE,
      '#default_value' => $sessionData['date'],
    ];

    $form['startTime'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start Time'),
      '#required' => TRUE,
      '#default_value' => substr($sessionData['start_time'], 0, 5),
      '#attributes' => ['placeholder' => 'HH:mm'],
    ];

    $form['endTime'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End Time'),
      '#required' => TRUE,
      '#default_value' => substr($sessionData['end_time'], 0, 5),
      '#attributes' => ['placeholder' => 'HH:mm'],
    ];

    $form['location'] = [
      '#type' => 'select',
      '#title' => $this->t('Location'),
      '#options' => $this->getLocationOptions(),
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
      '#default_value' => $sessionData['location_id'],
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'concept'   => $this->t('Concept'),
        'active'    => $this->t('Active'),
        'cancelled' => $this->t('Cancelled'),
        'full'      => $this->t('Full'),
      ],
      '#required' => TRUE,
      '#default_value' => $sessionData['status'],
    ];

    $form['capacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Capacity'),
      '#required' => TRUE,
      '#min' => 1,
      '#default_value' => $sessionData['capacity'],
      '#description' => $this->t('Maximum number of participants.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Session'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('session_management.list'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $sessionId = $form_state->getValue('sessionId');
    $title     = $form_state->getValue('title');
    $date      = $form_state->getValue('date');
    $locationId = $form_state->getValue('location');

    $startTime = $this->extractTime($form_state->getValue('startTime'));
    if ($startTime && strlen($startTime) === 5) {
      $startTime .= ':00';
    }
    $endTime = $this->extractTime($form_state->getValue('endTime'));
    if ($endTime && strlen($endTime) === 5) {
      $endTime .= ':00';
    }

    try {
      \Drupal::database()->update('session')
        ->fields([
          'title'       => $title,
          'date'        => $date,
          'start_time'  => $startTime,
          'end_time'    => $endTime,
          'location_id' => $locationId,
          'capacity'    => (int) $form_state->getValue('capacity'),
          'status'      => $form_state->getValue('status'),
        ])
        ->condition('session_id', $sessionId)
        ->execute();
      
      $this->messenger->addStatus($this->t('Session "@title" updated in database.', ['@title' => $title]));
    }
    catch (\Exception $e) {
      \Drupal::logger('session_management')->error('Failed to update session in DB: @err', ['@err' => $e->getMessage()]);
      $this->messenger->addError($this->t('Failed to update session locally.'));
    }
    
    $locationOptions = $this->getLocationOptions();
    $locationLabel = $locationId && isset($locationOptions[$locationId]) ? $locationOptions[$locationId] : $locationId;

    $message = new PlanningSessionUpdatedMessage(
      sessionId:    $sessionId,
      sessionName:  $title,
      changeType:   'updated',
      newTime:      $date . ' ' . $startTime,
      newStartTime: $startTime,
      newEndTime:   $endTime,
      newLocation:  $locationLabel,
      timestamp:    (new \DateTime())->format(\DateTime::ATOM),
    );

    $client = RabbitMQClient::fromEnv();
    try {
      $client->publish($message);
      $this->messenger->addStatus($this->t('Update for "@title" sent to planning.', [
        '@title' => $title,
      ]));
      $form_state->setRedirect('session_management.list');
    }
    catch (\RuntimeException $e) {
      \Drupal::logger('session_management')->error(
        'RabbitMQ session update failed: @err', ['@err' => $e->getMessage()]
      );
      $this->messenger->addError($this->t('Update could not be sent to planning. Please try again.'));
    }
    finally {
      $client->disconnect();
    }
  }

  /**
   * Returns location options from database.
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

}