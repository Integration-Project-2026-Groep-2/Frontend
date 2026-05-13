<?php

namespace Drupal\session_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hello_world\RabbitMQ\Message\Planning\PlanningLocationCreatedMessage;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;

/**
 * Form for creating a new location.
 */
class LocationCreateForm extends FormBase {

  public function getFormId(): string {
    return 'location_create_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['roomName'] = [
      '#type'      => 'textfield',
      '#title'     => $this->t('Room Name'),
      '#required'  => TRUE,
      '#maxlength' => 255,
    ];

    $form['capacity'] = [
      '#type'        => 'number',
      '#title'       => $this->t('Capacity'),
      '#required'    => TRUE,
      '#min'         => 1,
      '#description' => $this->t('Maximum number of participants.'),
    ];

    $form['address'] = [
      '#type'     => 'textfield',
      '#title'    => $this->t('Address'),
      '#required' => FALSE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Create Location'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $capacity = $form_state->getValue('capacity');
    if ($capacity < 1) {
      $form_state->setErrorByName('capacity', $this->t('Capacity must be at least 1.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $locationId = \Drupal\Component\Utility\Crypt::randomBytesBase64(16);
    // Use a standard UUID format if possible, or just a unique string.
    // Drupal's uuid service is better.
    $locationUuid = \Drupal::service('uuid')->generate();

    $roomName = $form_state->getValue('roomName');
    $capacity = (int) $form_state->getValue('capacity');
    $address  = $form_state->getValue('address') ?: NULL;

    try {
      \Drupal::database()->insert('location')
        ->fields([
          'location_id' => $locationUuid,
          'room_name'   => $roomName,
          'capacity'    => $capacity,
          'address'     => $address,
          'status'      => 'beschikbaar',
        ])
        ->execute();

      $this->messenger()->addStatus($this->t('Location "@name" saved to database.', ['@name' => $roomName]));
    }
    catch (\Exception $e) {
      \Drupal::logger('session_management')->error('Failed to save location to DB: @err', ['@err' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Failed to save location locally.'));
    }

    $message = new PlanningLocationCreatedMessage(
      roomName: $roomName,
      capacity: $capacity,
      address:  $address,
    );

    $client = RabbitMQClient::fromEnv();
    try {
      $client->publish($message);
      $this->messenger()->addStatus($this->t('Location "@name" sent to planning.', [
        '@name' => $roomName,
      ]));
      $form_state->setRedirect('session_management.location.list');
    }
    catch (\RuntimeException $e) {
      \Drupal::logger('session_management')->error(
        'RabbitMQ location publish failed: @err', ['@err' => $e->getMessage()]
      );
      $this->messenger()->addError($this->t('Location could not be sent to planning. Please try again.'));
    }
    finally {
      $client->disconnect();
    }
  }
}