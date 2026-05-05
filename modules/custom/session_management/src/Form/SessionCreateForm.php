<?php

namespace Drupal\session_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating a new session.
 */
class SessionCreateForm extends FormBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

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
    return 'session_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#required' => TRUE,
    ];

    $form['startTime'] = [
      '#type' => 'time',
      '#title' => $this->t('Start Time'),
      '#required' => TRUE,
    ];

    $form['endTime'] = [
      '#type' => 'time',
      '#title' => $this->t('End Time'),
      '#required' => TRUE,
    ];

    // Location select populated from getLocationOptions().
    $form['location'] = [
      '#type' => 'select',
      '#title' => $this->t('Location'),
      '#options' => $this->getLocationOptions(),
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
    ];

    // Button to add a new location (kept as before).
    $form['new_location_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Nieuwe locatie'),
      '#attributes' => ['class' => ['button', 'button--secondary', 'js-new-location']],
      // Add AJAX callback later: '#ajax' => ['callback' => '::openNewLocationModal'].
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
      '#default_value' => 'draft',
    ];

    $form['capacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Capacity'),
      '#required' => TRUE,
      '#min' => 1,
      '#description' => $this->t('Maximum number of participants.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Session'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Retrieve location options.
   *
   * TODO: implement RabbitMQ retrieval here. For now returns empty array.
   *
   * @return array<string,string>
   */
  protected function getLocationOptions(): array {
    // TODO: replace with RabbitMQ retrieval.
    return [
      'campus_kaai_c_1_1' => $this->t('Campus Kaai, blok C, verdieping 1, lokaal 1'),
      'campus_kaai_c_1_2' => $this->t('Campus Kaai, blok C, verdieping 1, lokaal 2'),
      'campus_kaai_c_1_3' => $this->t('Campus Kaai, blok C, verdieping 1, lokaal 3'),
    ];
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
    $this->messenger->addMessage($this->t('Session "@title" created successfully.', [
      '@title' => $form_state->getValue('title'),
    ]));

    // TODO: Send data to planning system via RabbitMQ, which will generate and return the sessionId.
  }

}