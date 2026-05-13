<?php

namespace Drupal\Tests\shift_bezoeker\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\shift_bezoeker\Form\CancelAccountConfirmForm;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

class CancelAccountConfirmFormTest extends KernelTestBase {

  use UserCreationTrait;

  protected static $modules = [
    'system',
    'user',
    'hello_world',
    'shift_bezoeker',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['user']);
    putenv('SHIFT_BEZOEKER_DISABLE_AMQP=1');
  }

  public function testConfirmBlocksAccount(): void {
    $account = $this->createUser([], 'tobedeleted', FALSE, ['mail' => 'gone@example.com']);
    $this->assertTrue($account->isActive(), 'User starts active');

    $this->setCurrentUser($account);

    $form_state = new FormState();
    $form_array = [];
    $form = \Drupal::classResolver(CancelAccountConfirmForm::class);
    $form->submitForm($form_array, $form_state);

    $reloaded = User::load($account->id());
    $this->assertTrue($reloaded->isBlocked(), 'User is blocked after cancel-form submit');

    $redirect = $form_state->getRedirect();
    $this->assertNotNull($redirect, 'Redirect is set after cancel');
    $this->assertSame('shift_bezoeker.account_verwijderd', $redirect->getRouteName());
  }

  public function testFormQuestionAndConfirmText(): void {
    $form = \Drupal::classResolver(CancelAccountConfirmForm::class);

    $this->assertNotEmpty((string) $form->getQuestion());
    $this->assertNotEmpty((string) $form->getConfirmText());
    $this->assertSame('shift_bezoeker.account', $form->getCancelUrl()->getRouteName());
  }

}
