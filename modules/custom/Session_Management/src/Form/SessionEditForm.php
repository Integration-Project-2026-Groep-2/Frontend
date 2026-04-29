<?php

namespace Drupal\Session_Management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SessionEditForm extends FormBase {

  public function getFormId() {
    return 'session_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $id = NULL) {
    #mock data
    $sessions = [
      '1' => [
        'title' => 'PHP Security Basics',
        'location' => 'Room A',
        'speaker' => 'Acme Corp',
        'capacity' => 50,
      ],
      '2' => [
        'title' => 'Web Performance Tips',
        'location' => 'Room B',
        'speaker' => 'Tech Solutions',
        'capacity' => 30,
      ],
    ];

    $current = $sessions[$id] ?? [
      'title' => '',
      'location' => '',
      'speaker' => '',
      'capacity' => '',
    ];

    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $current['title'],
      '#required' => TRUE,
    ];

    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#default_value' => $current['location'],
      '#required' => TRUE,
    ];

    $form['speaker'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Speaker'),
      '#default_value' => $current['speaker'],
    ];

    $form['capacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Capacity'),
      '#default_value' => $current['capacity'],
      '#min' => 1,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save changes'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: save updated session.
    $this->messenger()->addStatus($this->t('Session updated.'));
  }

}