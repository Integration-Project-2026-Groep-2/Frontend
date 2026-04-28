<?php

namespace Drupal\hello_world\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hello_world\RabbitMQ\Message\RegistrationMessage;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;
use Drupal\user\Entity\User;

class RegisterVisitorForm extends FormBase {

  public function getFormId(): string {
    return 'register_visitor_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['first_name'] = [
      '#type'     => 'textfield',
      '#title'    => $this->t('First Name'),
      '#required' => TRUE,
    ];
    $form['last_name'] = [
      '#type'     => 'textfield',
      '#title'    => $this->t('Last Name'),
      '#required' => TRUE,
    ];
    $form['email'] = [
      '#type'     => 'email',
      '#title'    => $this->t('Email'),
      '#required' => TRUE,
    ];
    $form['phone'] = [
      '#type'     => 'tel',
      '#title'    => $this->t('Phone'),
      '#required' => FALSE,
    ];
    $form['pass'] = [
      '#type'        => 'password_confirm',
      '#required'    => TRUE,
      '#description' => $this->t('Minimum 8 characters.'),
    ];
    $form['gdpr_consent'] = [
      '#type'     => 'checkbox',
      '#title'    => $this->t('I agree to the GDPR terms'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Register'),
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $email = $form_state->getValue('email');
    $phone = $form_state->getValue('phone');
    $pass  = $form_state->getValue('pass');

    if ($email && user_load_by_mail($email)) {
      $form_state->setErrorByName('email', $this->t('This email address is already registered.'));
    }

    if (!empty($phone) && strlen(preg_replace('/\D/', '', $phone)) < 10) {
      $form_state->setErrorByName('phone', $this->t('Phone number must be at least 10 digits.'));
    }

    if (strlen($pass) < 8) {
      $form_state->setErrorByName('pass', $this->t('Password must be at least 8 characters.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $email     = $form_state->getValue('email');
    $firstName = $form_state->getValue('first_name');
    $lastName  = $form_state->getValue('last_name');
    $phone     = $form_state->getValue('phone') ?: NULL;
    $gdpr      = (bool) $form_state->getValue('gdpr_consent');
    $password  = $form_state->getValue('pass');

    // ── 1. Drupal user aanmaken ───────────────────────────────────────────
    try {
      $account = User::create([
        // name = email zodat Drupal intern uniek blijft maar de gebruiker
        // via e-mail inlogt (zie hello_world_form_alter hieronder).
        'name'   => $email,
        'mail'   => $email,
        'pass'   => $password,
        'status' => 1,
      ]);

      // Wijs de Drupal-rol 'visitor' toe (moet al aangemaakt zijn).
      $account->addRole('visitor');

      $this->setField($account, 'field_first_name',   $firstName);
      $this->setField($account, 'field_last_name',    $lastName);
      $this->setField($account, 'field_phone',        $phone);
      $this->setField($account, 'field_gdpr_consent', $gdpr);

      $account->save();
    }
    catch (\Exception $e) {
      \Drupal::logger('hello_world')->error(
        'Drupal user aanmaken mislukt: @err', ['@err' => $e->getMessage()]
      );
      $this->messenger()->addError($this->t('Registration failed. Please try again.'));
      return;
    }

    // ── 2. RabbitMQ bericht sturen ────────────────────────────────────────
    $message = new RegistrationMessage(
      firstName:   $firstName,
      lastName:    $lastName,
      email:       $email,
      gdprConsent: $gdpr,
      phone:       $phone,
      role:        'visitor',   // altijd visitor bij zelfregistratie
    );

    $client = RabbitMQClient::fromEnv();
    try {
      $client->publish($message);
    }
    catch (\RuntimeException $e) {
      \Drupal::logger('hello_world')->error(
        'RabbitMQ publish mislukt: @err', ['@err' => $e->getMessage()]
      );
    }
    finally {
      $client->disconnect();
    }

    // ── 3. Redirect naar login ────────────────────────────────────────────
    $this->messenger()->addStatus(
      $this->t('Registration successful! You can now log in with your email address.')
    );
    $form_state->setRedirectUrl(Url::fromRoute('user.login'));
  }

  private function setField(object $entity, string $field, mixed $value): void {
    if ($value !== NULL && $entity->hasField($field)) {
      $entity->set($field, $value);
    }
  }

}
