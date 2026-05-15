<?php

namespace Drupal\shift_bezoeker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hello_world\RabbitMQ\Message\Company\CompanyCreatedMessage;
use Drupal\hello_world\RabbitMQ\Message\Registration\RegistrationMessage;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;
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

    // Persoonlijke gegevens: gedeeld door beide flows. Bedrijven bedrijfs-
    // accounts hebben ALTIJD een contact-persoon (de mens die zich namens
    // het bedrijf registreert). Zonder deze velden krijgt de SF Contact de
    // bedrijfsnaam als firstName, een data-model fout.
    $form['persoonlijk_header'] = [
      '#markup' => '<h2 class="form-section-title">' . $this->t('Persoonlijke Gegevens') . '</h2>',
    ];

    $form['name_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['name_row']['firstName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];

    $form['name_row']['lastName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];

    $form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone (Optional)'),
    ];

    // --- BEZOEKER WRAPPER ---
    $form['bezoeker_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [':input[name="registratie_type"]' => ['value' => 'bezoeker']],
      ],
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

    if (empty($values['firstName'])) {
      $form_state->setErrorByName('firstName', $this->t('Voornaam is verplicht.'));
    }
    if (empty($values['lastName'])) {
      $form_state->setErrorByName('lastName', $this->t('Achternaam is verplicht.'));
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

    $userData->set('shift_bezoeker', $account->id(), 'first_name', $values['firstName']);
    $userData->set('shift_bezoeker', $account->id(), 'last_name', $values['lastName']);
    if (!empty($values['phone'])) {
      $userData->set('shift_bezoeker', $account->id(), 'phone', $values['phone']);
    }

    if ($type === 'bedrijf') {
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

    $this->publishRegistrationEvents($values);

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

  /**
   * Publishes Registration (+ CompanyCreated for bedrijf) on user.topic.
   *
   * Soft-fail: any RabbitMQ / XSD failure is logged but does NOT roll back
   * the Drupal account. Eventual-consistency tradeoff confirmed by Lars
   * (plan 2026-05-09) — registration UX is decoupled from broker uptime.
   */
  private function publishRegistrationEvents(array $values): void {
    // Test-isolation hook: kernel tests set this so they don't open AMQP
    // sockets (RabbitMQClient retries 10x5s on connect failure → too slow
    // for a unit/kernel run). Production never sets it.
    if (!empty($_ENV['SHIFT_BEZOEKER_DISABLE_AMQP']) || getenv('SHIFT_BEZOEKER_DISABLE_AMQP')) {
      return;
    }

    try {
      $client = RabbitMQClient::fromEnv();
      foreach (self::buildMessages($values) as $message) {
        $client->publish($message);
      }
      $client->disconnect();
    }
    catch (\Throwable $e) {
      \Drupal::logger('shift_bezoeker')->error(
        'AMQP publish failed for @type registration (@email): @msg',
        [
          '@type'  => $values['registratie_type'] ?? '?',
          '@email' => $values['email'] ?? '?',
          '@msg'   => $e->getMessage(),
        ],
      );
    }
  }

  /**
   * Pure mapping from form-values to MessageInterface[] — testable without I/O.
   *
   * Bezoeker  → 1 RegistrationMessage
   * Bedrijf   → 1 RegistrationMessage (role=company_contact) + 1 CompanyCreatedMessage
   *
   * @return \Drupal\hello_world\RabbitMQ\Message\MessageInterface[]
   */
  public static function buildMessages(array $values): array {
    $type     = $values['registratie_type'] ?? 'bezoeker';
    $messages = [];

    $messages[] = new RegistrationMessage(
      firstName:   (string) ($values['firstName'] ?? ''),
      lastName:    (string) ($values['lastName']  ?? ''),
      email:       (string) ($values['email'] ?? ''),
      gdprConsent: (bool)   ($values['gdpr_consent'] ?? FALSE),
      phone:       !empty($values['phone'])       ? (string) $values['phone']       : NULL,
      company:     !empty($values['companyName']) ? (string) $values['companyName'] : NULL,
      role:        self::mapRole($values),
    );

    if ($type === 'bedrijf') {
      $messages[] = new CompanyCreatedMessage(
        name:      (string) ($values['companyName'] ?? ''),
        vatNumber: strtoupper((string) ($values['vatNumber'] ?? '')),
        email:     !empty($values['email'])  ? (string) $values['email']  : NULL,
        street:    !empty($values['street']) ? (string) $values['street'] : NULL,
        city:      !empty($values['city'])   ? (string) $values['city']   : NULL,
        country:   'BE',
      );
    }

    return $messages;
  }

  /**
   * Maps form role-values to the frontend-contract.xsd enum.
   *   form 'visitor' / null      → 'VISITOR'
   *   form 'speaker'             → 'SPREKER'
   *   form 'kassa'               → 'KASSAMEDEWERKER'
   *   bedrijf flow (no role)     → 'COMPANY_CONTACT'
   */
  private static function mapRole(array $values): string {
    if (($values['registratie_type'] ?? '') === 'bedrijf') {
      return 'COMPANY_CONTACT';
    }
    return match ($values['role'] ?? 'visitor') {
      'speaker' => 'SPREKER',
      'kassa'   => 'KASSAMEDEWERKER',
      default   => 'VISITOR',
    };
  }
}
