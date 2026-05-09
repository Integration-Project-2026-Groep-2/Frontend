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
    return $this->t('Je account wordt geblokkeerd en je wordt uitgelogd. Je kunt niet meer inloggen met deze gegevens.');
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

    $account->block();
    $account->save();

    $this->publishCancelEvent($uid, $email);

    \Drupal::messenger()->addStatus($this->t('Je account is verwijderd.'));

    user_logout();
    $form_state->setRedirectUrl(Url::fromUri('internal:/'));
  }

  /**
   * Soft-fails on broker downtime — account block proceeds regardless.
   * Mirrors RegistratieForm::publishRegistrationEvents pattern.
   */
  private function publishCancelEvent(int $uid, string $email): void {
    if (!empty($_ENV['SHIFT_BEZOEKER_DISABLE_AMQP']) || getenv('SHIFT_BEZOEKER_DISABLE_AMQP')) {
      return;
    }

    $message = new RegistrationChangeMessage(
      email:      $email,
      sessionId:  (string) $uid,
      changeType: 'cancelled',
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
