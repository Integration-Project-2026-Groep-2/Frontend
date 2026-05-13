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
      '#type'     => 'time',
      '#title'    => $this->t('Start Time'),
      '#required' => TRUE,
    ];

    $form['endTime'] = [
      '#type'     => 'time',
      '#title'    => $this->t('End Time'),
      '#required' => TRUE,
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
    return [
      'campus_kaai_c_1_1' => $this->t('Campus Kaai, blok C, verdieping 1, lokaal 1'),
      'campus_kaai_c_1_2' => $this->t('Campus Kaai, blok C, verdieping 1, lokaal 2'),
      'campus_kaai_c_1_3' => $this->t('Campus Kaai, blok C, verdieping 1, lokaal 3'),
    ];
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
    $startTime = $form_state->getValue('startTime');
    $endTime   = $form_state->getValue('endTime');

    if ($startTime && $endTime && $startTime >= $endTime) {
      $form_state->setErrorByName('endTime', $this->t('End time must be after start time.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $date      = $form_state->getValue('date');
    $startTime = $form_state->getValue('startTime');
    $endTime   = $form_state->getValue('endTime');
    $speakerId = $form_state->getValue('speaker') ?: NULL;

    // Resolve speaker UUID — planning verwacht een UUID, niet een e-mail.
    // TODO: vervang door echte planning UUID als die beschikbaar is.
    $speakerUuid = NULL;
    if ($speakerId) {
      $speaker = \Drupal::entityTypeManager()->getStorage('user')->load($speakerId);
      if ($speaker) {
        $speakerUuid = $speaker->uuid();
      }
    }

    $message = new PlanningSessionCreatedMessage(
      title:      $form_state->getValue('title'),
      date:       $date,
      startTime:  $startTime . ':00',
      endTime:    $endTime . ':00',
      capacity:   (int) $form_state->getValue('capacity'),
      locationId: $form_state->getValue('location') ?: NULL,
      speakerId:  $speakerUuid,
      status:     $form_state->getValue('status'),
      timestamp:  (new \DateTime())->format(\DateTime::ISO8601),
    );

    $client = RabbitMQClient::fromEnv();
    try {
      $client->publish($message);
      $this->messenger->addStatus($this->t('Session "@title" created and sent to planning.', [
        '@title' => $form_state->getValue('title'),
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