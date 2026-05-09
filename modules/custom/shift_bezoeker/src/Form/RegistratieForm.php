<?php

namespace Drupal\shift_bezoeker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

class RegistratieForm extends FormBase {

  public function getFormId() {
    return 'shift_bezoeker_registratie_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'shift_theme/global-styling';

    $form['registratie_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Registreer als:'),
      '#options' => [
        'bezoeker' => $this->t('Bezoeker'),
        'bedrijf' => $this->t('Bedrijf'),
      ],
      '#default_value' => 'bezoeker',
    ];

    // Account-sectie: email + password gedeeld door beide flows. Bedrijven
    // hadden voorheen GEEN email-veld waardoor User::create sowieso
    // onmogelijk was.
    $form['account_header'] = [
      '#markup' => '<h2 class="form-section-title">' . $this->t('Account') . '</h2>',
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
    ];

    $form['pass'] = [
      '#type' => 'password_confirm',
      '#required' => TRUE,
      '#description' => $this->t('Minstens 8 karakters.'),
    ];

    // --- BEZOEKER WRAPPER ---
    $form['bezoeker_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [':input[name="registratie_type"]' => ['value' => 'bezoeker']],
      ],
    ];

    $form['bezoeker_wrapper']['persoonlijk_header'] = [
      '#markup' => '<h2 class="form-section-title">' . $this->t('Persoonlijke Gegevens') . '</h2>',
    ];

    $form['bezoeker_wrapper']['name_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bezoeker_wrapper']['name_row']['firstName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bezoeker']]],
    ];

    $form['bezoeker_wrapper']['name_row']['lastName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bezoeker']]],
    ];

    $form['bezoeker_wrapper']['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone (Optional)'),
    ];

    $form['bezoeker_wrapper']['event_header'] = [
      '#markup' => '<h2 class="form-section-title">' . $this->t('Event Details') . '</h2>',
    ];

    // Rol-IDs matchen custom_roles module (visitor/speaker/kassa). Vorige
    // form had 'staff' wat geen bestaande rol was.
    $form['bezoeker_wrapper']['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Rol'),
      '#options' => [
        'visitor' => $this->t('Bezoeker'),
        'speaker' => $this->t('Spreker'),
        'kassa' => $this->t('Kassamedewerker'),
      ],
      '#empty_option' => $this->t('Kies een rol...'),
    ];

    // --- BEDRIJF WRAPPER ---
    $form['bedrijf_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [':input[name="registratie_type"]' => ['value' => 'bedrijf']],
      ],
    ];

    $form['bedrijf_wrapper']['company_header'] = [
      '#markup' => '<h2 class="form-section-title">' . $this->t('Bedrijfsgegevens') . '</h2>',
    ];

    $form['bedrijf_wrapper']['company_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bedrijf_wrapper']['company_row']['companyName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company Name'),
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bedrijf']]],
    ];

    $form['bedrijf_wrapper']['company_row']['vatNumber'] = [
      '#type' => 'textfield',
      '#title' => $this->t('VAT Number'),
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bedrijf']]],
    ];

    $form['bedrijf_wrapper']['address_header'] = [
      '#markup' => '<h2 class="form-section-title">' . $this->t('Adresgegevens') . '</h2>',
    ];

    $form['bedrijf_wrapper']['address_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bedrijf_wrapper']['address_row']['street'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street'),
    ];

    $form['bedrijf_wrapper']['address_row']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
    ];

    // GDPR universeel — bezoekers én bedrijven moeten akkoord gaan.
    $form['gdpr_consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree to the GDPR terms'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Registreer!'),
      '#attributes' => ['class' => ['btn-primary']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $type = $values['registratie_type'];
    $email = trim($values['email'] ?? '');
    $password = $values['pass'] ?? '';

    if ($email === '') {
      $form_state->setErrorByName('email', $this->t('E-mailadres is verplicht.'));
    }
    elseif ($this->emailExists($email)) {
      $form_state->setErrorByName('email', $this->t('Dit e-mailadres is al geregistreerd.'));
    }

    if (strlen($password) < 8) {
      $form_state->setErrorByName('pass', $this->t('Wachtwoord moet minstens 8 karakters bevatten.'));
    }

    if ($type === 'bezoeker') {
      if (empty($values['firstName'])) {
        $form_state->setErrorByName('firstName', $this->t('Voornaam is verplicht.'));
      }
      if (empty($values['lastName'])) {
        $form_state->setErrorByName('lastName', $this->t('Achternaam is verplicht.'));
      }
    }

    if ($type === 'bedrijf') {
      if (empty($values['companyName'])) {
        $form_state->setErrorByName('companyName', $this->t('Bedrijfsnaam is verplicht.'));
      }
      $vat = trim($values['vatNumber'] ?? '');
      if ($vat === '') {
        $form_state->setErrorByName('vatNumber', $this->t('BTW-nummer is verplicht.'));
      }
      elseif (!str_starts_with(strtoupper($vat), 'BE')) {
        $form_state->setErrorByName('vatNumber', $this->t('Een Belgisch BTW-nummer moet beginnen met BE.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $type = $values['registratie_type'];
    $email = trim($values['email']);
    $password = $values['pass'];
    $gdpr = (bool) $values['gdpr_consent'];

    $account = User::create([
      'name' => $email,
      'mail' => $email,
      'pass' => $password,
      'status' => 1,
    ]);

    if ($type === 'bezoeker') {
      // Default 'visitor' als de event-rol-select leeg is.
      $eventRole = $values['role'] ?: 'visitor';
      $account->addRole($eventRole);
    }
    else {
      $account->addRole('company');
    }

    try {
      $account->save();
    }
    catch (\Exception $e) {
      \Drupal::logger('shift_bezoeker')->error('Registratie failed: @msg', ['@msg' => $e->getMessage()]);
      \Drupal::messenger()->addError($this->t('Er ging iets mis bij het aanmaken van je account. Probeer opnieuw of contacteer support.'));
      return;
    }

    $userData = \Drupal::service('user.data');
    $userData->set('shift_bezoeker', $account->id(), 'gdpr_consent', $gdpr ? 1 : 0);

    if ($type === 'bezoeker') {
      $userData->set('shift_bezoeker', $account->id(), 'first_name', $values['firstName']);
      $userData->set('shift_bezoeker', $account->id(), 'last_name', $values['lastName']);
      if (!empty($values['phone'])) {
        $userData->set('shift_bezoeker', $account->id(), 'phone', $values['phone']);
      }
    }
    else {
      $userData->set('shift_bezoeker', $account->id(), 'company_name', $values['companyName']);
      $userData->set('shift_bezoeker', $account->id(), 'vat_number', strtoupper($values['vatNumber']));
      if (!empty($values['street'])) {
        $userData->set('shift_bezoeker', $account->id(), 'street', $values['street']);
      }
      if (!empty($values['city'])) {
        $userData->set('shift_bezoeker', $account->id(), 'city', $values['city']);
      }
    }

    user_login_finalize($account);

    \Drupal::messenger()->addStatus($this->t('Welkom! Je account is aangemaakt en je bent ingelogd.'));

    // Redirect-target matcht LoginRedirectionSubscriber's logica.
    $roles = $account->getRoles();
    if (in_array('speaker', $roles, TRUE)) {
      $form_state->setRedirectUrl(Url::fromUri('internal:/bespreker'));
    }
    else {
      $form_state->setRedirectUrl(Url::fromUri('internal:/home'));
    }
  }

  private function emailExists(string $email): bool {
    $accounts = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    return !empty($accounts);
  }
}
