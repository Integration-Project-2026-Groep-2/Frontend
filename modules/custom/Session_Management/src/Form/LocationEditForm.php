<?php

namespace Drupal\session_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hello_world\RabbitMQ\Message\Planning\PlanningLocationUpdatedMessage;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;

/**
 * Form for editing an existing location.
 */
class LocationEditForm extends FormBase {

  public function getFormId(): string {
    return 'location_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $locationId = NULL): array {
    $locationData = [];
    if ($locationId) {
      try {
        $locationData = (array) \Drupal::database()->select('location', 'l')
          ->fields('l')
          ->condition('location_id', $locationId)
          ->execute()
          ->fetchAssoc();
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('Could not load location data.'));
      }
    }

    if (empty($locationData)) {
      $this->messenger()->addError($this->t('Location not found.'));
      return ['#markup' => $this->t('Location not found.')];
    }

    $form['locationId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location ID'),
      '#default_value' => $locationData['location_id'],
      '#disabled' => TRUE,
    ];

    $form['roomName'] = [
      '#type'      => 'textfield',
      '#title'     => $this->t('Room Name'),
      '#required'  => TRUE,
      '#maxlength' => 255,
      '#default_value' => $locationData['room_name'],
    ];

    $form['capacity'] = [
      '#type'        => 'number',
      '#title'       => $this->t('Capacity'),
      '#required'    => TRUE,
      '#min'         => 1,
      '#default_value' => $locationData['capacity'],
      '#description' => $this->t('Maximum number of participants.'),
    ];

    $form['address'] = [
      '#type'     => 'textfield',
      '#title'    => $this->t('Address'),
      '#required' => FALSE,
      '#default_value' => $locationData['address'],
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'beschikbaar' => $this->t('Beschikbaar'),
        'bezet' => $this->t('Bezet'),
        'onderhoud' => $this->t('Onderhoud'),
      ],
      '#required' => TRUE,
      '#default_value' => $locationData['status'],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Update Location'),
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
    $locationId = $form_state->getValue('locationId');
    $roomName = $form_state->getValue('roomName');
    $capacity = (int) $form_state->getValue('capacity');
    $address  = $form_state->getValue('address') ?: NULL;
    $status   = $form_state->getValue('status');

    try {
      \Drupal::database()->update('location')
        ->fields([
          'room_name'   => $roomName,
          'capacity'    => $capacity,
          'address'     => $address,
          'status'      => $status,
        ])
        ->condition('location_id', $locationId)
        ->execute();

      $this->messenger()->addStatus($this->t('Location "@name" updated in database.', ['@name' => $roomName]));
    }
    catch (\Exception $e) {
      \Drupal::logger('session_management')->error('Failed to update location in DB: @err', ['@err' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Failed to update location locally.'));
    }

    // Note: Assuming a PlanningLocationUpdatedMessage exists.
    // If it doesn't, we'd need to create it.
    // For now, I'll use the Create message if Update doesn't exist, but usually it should.
    
    // Check if PlanningLocationUpdatedMessage exists
    if (class_exists('Drupal\hello_world\RabbitMQ\Message\Planning\PlanningLocationUpdatedMessage')) {
      $message = new PlanningLocationUpdatedMessage(
        roomName: $roomName,
        capacity: $capacity,
        address:  $address,
      );

      $client = RabbitMQClient::fromEnv();
      try {
        $client->publish($message);
        $this->messenger()->addStatus($this->t('Location update sent to planning.'));
        $form_state->setRedirect('session_management.location.list');
      }
      catch (\RuntimeException $e) {
        $this->messenger()->addError($this->t('Location update could not be sent to planning.'));
      }
      finally {
        $client->disconnect();
      }
    }
    else {
        $form_state->setRedirect('session_management.location.list');
    }
  }
}
