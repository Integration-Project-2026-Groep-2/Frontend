<?php

namespace Drupal\shift_bezoeker\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hello_world\RabbitMQ\Message\Registration\RegistrationChangeMessage;
use Drupal\hello_world\RabbitMQ\RabbitMQClient;
use Drupal\user\Entity\User;

class CancelAccountConfirmForm extends ConfirmFormBase {

  public function getFormId() {
    return 'shift_bezoeker_cancel_account_confirm_form';
  }

  public function getQuestion() {
    return $this->t('Weet je zeker dat je je account wilt verwijderen?');
  }

  public function getDescription() {
    return $this->t('Je account wordt verwijderd en je wordt uitgelogd. Je kunt niet meer inloggen met deze gegevens. Dit kan niet ongedaan worden gemaakt.');
  }

  public function getCancelUrl() {
    return Url::fromRoute('shift_bezoeker.account');
  }

  public function getConfirmText() {
    return $this->t('Account verwijderen');
  }

  public function getCancelText() {
    return $this->t('Annuleren');
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = (int) $this->currentUser()->id();
    $account = User::load($uid);

    if (!$account) {
      \Drupal::messenger()->addError($this->t('Account niet gevonden.'));
      $form_state->setRedirectUrl(Url::fromRoute('shift_bezoeker.account'));
      return;
    }

    $email = $account->getEmail();

    $crmId = ($account->hasField('field_crm_id') && !$account->get('field_crm_id')->isEmpty())
      ? (string) $account->get('field_crm_id')->value
      : NULL;

    $account->block();
    $account->save();

    $this->publishCancelEvent($uid, $email, $crmId);

    user_logout();
    $form_state->setRedirectUrl(Url::fromRoute('shift_bezoeker.account_verwijderd'));
  }

  /**
   * Soft-fails on broker downtime — account block proceeds regardless.
   * Mirrors RegistratieForm::publishRegistrationEvents pattern.
   */
  private function publishCancelEvent(int $uid, string $email, ?string $crmId): void {
    if (!empty($_ENV['SHIFT_BEZOEKER_DISABLE_AMQP']) || getenv('SHIFT_BEZOEKER_DISABLE_AMQP')) {
      return;
    }

    $message = new RegistrationChangeMessage(
      email:          $email,
      sessionId:      (string) $uid,
      changeType:     'cancelled',
      registrationId: $crmId,
    );

    $client = RabbitMQClient::fromEnv();
    try {
      $client->publish($message);
      \Drupal::logger('shift_bezoeker')->info(
        'AMQP published account cancel for @email',
        ['@email' => $email],
      );
    }
    catch (\Throwable $e) {
      \Drupal::logger('shift_bezoeker')->error(
        'AMQP publish failed for account cancel (@email): @msg',
        ['@email' => $email, '@msg' => $e->getMessage()],
      );
    }
    finally {
      $client->disconnect();
    }
  }

}
