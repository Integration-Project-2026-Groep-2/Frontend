<?php

namespace Drupal\shift_bezoeker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hello_world\RabbitMQ\Message\Registration\RegistrationChangeMessage;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;
use Drupal\user\Entity\User;

class EditAccountForm extends FormBase {

  public function getFormId() {
    return 'shift_bezoeker_edit_account_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'shift_theme/global-styling';

    $uid = (int) $this->currentUser()->id();
    $userData = \Drupal::service('user.data');
    $account = User::load($uid);
    $email = $account ? $account->getEmail() : '';

    $firstName   = (string) ($userData->get('shift_bezoeker', $uid, 'first_name')   ?? '');
    $lastName    = (string) ($userData->get('shift_bezoeker', $uid, 'last_name')    ?? '');
    $phone       = (string) ($userData->get('shift_bezoeker', $uid, 'phone')        ?? '');
    $companyName = (string) ($userData->get('shift_bezoeker', $uid, 'company_name') ?? '');

    $form['title'] = [
      '#markup' => '<h1 class="page-title">' . $this->t('Mijn Profiel') . '</h1>',
    ];

    $form['email_display'] = [
      '#type' => 'item',
      '#title' => $this->t('Email Address'),
      '#markup' => '<p>' . htmlspecialchars($email) . '</p>',
      '#description' => $this->t('Email kan niet gewijzigd worden via dit formulier.'),
    ];

    $form['name_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['name_row']['firstName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
      '#default_value' => $firstName,
    ];

    $form['name_row']['lastName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
      '#default_value' => $lastName,
    ];

    $form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone (Optional)'),
      '#default_value' => $phone,
    ];

    if ($companyName !== '') {
      $form['companyName'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Company Name'),
        '#default_value' => $companyName,
      ];
    }

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-actions']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Opslaan'),
      '#attributes' => ['class' => ['btn-primary']],
    ];

    $form['actions']['cancel_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Account verwijderen'),
      '#url' => Url::fromRoute('shift_bezoeker.account_cancel'),
      '#attributes' => ['class' => ['btn-danger']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (trim((string) $form_state->getValue('firstName')) === '') {
      $form_state->setErrorByName('firstName', $this->t('Voornaam is verplicht.'));
    }
    if (trim((string) $form_state->getValue('lastName')) === '') {
      $form_state->setErrorByName('lastName', $this->t('Achternaam is verplicht.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = (int) $this->currentUser()->id();
    $userData = \Drupal::service('user.data');

    $newValues = [
      'first_name'   => trim((string) $form_state->getValue('firstName')),
      'last_name'    => trim((string) $form_state->getValue('lastName')),
      'phone'        => trim((string) ($form_state->getValue('phone')       ?? '')),
      'company_name' => trim((string) ($form_state->getValue('companyName') ?? '')),
    ];

    $diff = [];
    foreach ($newValues as $key => $value) {
      $oldValue = (string) ($userData->get('shift_bezoeker', $uid, $key) ?? '');
      if ($value !== $oldValue) {
        $userData->set('shift_bezoeker', $uid, $key, $value);
        $diff[$key] = $value;
      }
    }

    if (!empty($diff)) {
      $this->publishChangeEvent($uid, $diff);
      \Drupal::messenger()->addStatus($this->t('Profiel bijgewerkt.'));
    }
    else {
      \Drupal::messenger()->addStatus($this->t('Geen wijzigingen om op te slaan.'));
    }

    $form_state->setRedirectUrl(Url::fromRoute('shift_bezoeker.account'));
  }

  /**
   * Soft-fails on broker downtime — UserData persistence is not rolled back.
   * Mirrors RegistratieForm::publishRegistrationEvents pattern.
   */
  private function publishChangeEvent(int $uid, array $diff): void {
    if (!empty($_ENV['SHIFT_BEZOEKER_DISABLE_AMQP']) || getenv('SHIFT_BEZOEKER_DISABLE_AMQP')) {
      return;
    }

    $account = User::load($uid);
    if (!$account) {
      return;
    }

    $message = new RegistrationChangeMessage(
      email:      $account->getEmail(),
      sessionId:  (string) $uid,
      changeType: 'updated',
      firstName:  $diff['first_name']   ?? NULL,
      lastName:   $diff['last_name']    ?? NULL,
      phone:      $diff['phone']        ?? NULL,
      company:    $diff['company_name'] ?? NULL,
    );

    $client = RabbitMQClient::fromEnv();
    try {
      $client->publish($message);
      \Drupal::logger('shift_bezoeker')->info(
        'AMQP published profile update for @email (fields: @fields)',
        ['@email' => $account->getEmail(), '@fields' => implode(',', array_keys($diff))],
      );
    }
    catch (\Throwable $e) {
      \Drupal::logger('shift_bezoeker')->error(
        'AMQP publish failed for profile update (@email): @msg',
        ['@email' => $account->getEmail(), '@msg' => $e->getMessage()],
      );
    }
    finally {
      $client->disconnect();
    }
  }

}
