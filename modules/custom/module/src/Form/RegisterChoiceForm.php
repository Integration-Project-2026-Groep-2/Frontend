<?php

namespace Drupal\hello_world\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class RegisterChoiceForm extends FormBase {

  public function getFormId(): string {
    return 'register_choice_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['register_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Register as'),
      '#options' => [
        'visitor' => $this->t('Visitor'),
        'company' => $this->t('Company'),
      ],
      '#default_value' => $form_state->getValue('register_type') ?? '',
      '#ajax' => [
        'callback' => '::ajaxLoadRegistrationForm',
        'wrapper' => 'registration-form-wrapper',
        'event' => 'change',
      ],
      '#required' => TRUE,
    ];

    $form['registration_form'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'registration-form-wrapper',
      ],
    ];

    if ($type = $form_state->getValue('register_type')) {
      $form['registration_form']['form'] = match ($type) {
        'company' => \Drupal::formBuilder()->getForm('\Drupal\hello_world\Form\RegisterCompanyForm'),
        default => \Drupal::formBuilder()->getForm('\Drupal\hello_world\Form\RegisterVisitorForm'),
      };
    }

    return $form;
  }

  public function ajaxLoadRegistrationForm(array &$form, FormStateInterface $form_state): array {
    return $form['registration_form'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // No submit action needed on this selector form.
  }

}