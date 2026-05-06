<?php
<?php

namespace Drupal\session_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    // TODO: Load session data from database/RabbitMQ based on $sessionId.
    $sessionData = [];

    $form['sessionId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Session ID'),
      '#default_value' => $sessionData['sessionId'] ?? $sessionId,
      '#disabled' => TRUE,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $sessionData['title'] ?? '',
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#required' => TRUE,
      '#default_value' => $sessionData['date'] ?? '',
    ];

    $form['startTime'] = [
      '#type' => 'time',
      '#title' => $this->t('Start Time'),
      '#required' => TRUE,
      '#default_value' => $sessionData['startTime'] ?? '',
    ];

    $form['endTime'] = [
      '#type' => 'time',
      '#title' => $this->t('End Time'),
      '#required' => TRUE,
      '#default_value' => $sessionData['endTime'] ?? '',
    ];

    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $sessionData['location'] ?? '',
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'draft' => $this->t('Draft'),
        'scheduled' => $this->t('Scheduled'),
        'active' => $this->t('Active'),
        'cancelled' => $this->t('Cancelled'),
      ],
      '#required' => TRUE,
      '#default_value' => $sessionData['status'] ?? 'draft',
    ];

    $form['capacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Capacity'),
      '#required' => TRUE,
      '#min' => 1,
      '#default_value' => $sessionData['capacity'] ?? '',
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
    $startTime = $form_state->getValue('startTime');
    $endTime = $form_state->getValue('endTime');

    if ($startTime && $endTime && $startTime >= $endTime) {
      $form_state->setErrorByName('endTime', $this->t('End time must be after start time.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger->addMessage($this->t('Session "@title" updated successfully.', [
      '@title' => $form_state->getValue('title'),
    ]));

    // TODO: Update session data via RabbitMQ or database.
    $form_state->setRedirect('session_management.list');
  }

}