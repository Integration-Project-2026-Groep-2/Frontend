<?php

namespace Drupal\Tests\shift_bezoeker\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\shift_bezoeker\Form\EditAccountForm;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

class EditAccountFormTest extends KernelTestBase {

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

  public function testSubmitPersistsChangedFieldsOnly(): void {
    $account = $this->createUser([], 'tester', FALSE, ['mail' => 'tester@example.com']);
    $userData = \Drupal::service('user.data');
    $userData->set('shift_bezoeker', $account->id(), 'first_name', 'OldFirst');
    $userData->set('shift_bezoeker', $account->id(), 'last_name',  'OldLast');
    $userData->set('shift_bezoeker', $account->id(), 'phone',      '+32400000000');

    $this->setCurrentUser($account);

    $form_state = (new FormState())->setValues([
      'firstName'   => 'NewFirst',
      'lastName'    => 'OldLast',
      'phone'       => '+32400000000',
      'companyName' => '',
    ]);

    $form_array = [];
    $form = \Drupal::classResolver(EditAccountForm::class);
    $form->submitForm($form_array, $form_state);

    $this->assertSame('NewFirst', $userData->get('shift_bezoeker', $account->id(), 'first_name'));
    $this->assertSame('OldLast',  $userData->get('shift_bezoeker', $account->id(), 'last_name'));
    $this->assertSame('+32400000000', $userData->get('shift_bezoeker', $account->id(), 'phone'));
  }

  public function testSubmitWithNoChangesIsNoOp(): void {
    $account = $this->createUser([], 'tester2', FALSE, ['mail' => 'tester2@example.com']);
    $userData = \Drupal::service('user.data');
    $userData->set('shift_bezoeker', $account->id(), 'first_name', 'Same');
    $userData->set('shift_bezoeker', $account->id(), 'last_name',  'Same');

    $this->setCurrentUser($account);

    $form_state = (new FormState())->setValues([
      'firstName'   => 'Same',
      'lastName'    => 'Same',
      'phone'       => '',
      'companyName' => '',
    ]);

    $form_array = [];
    $form = \Drupal::classResolver(EditAccountForm::class);
    $form->submitForm($form_array, $form_state);

    $this->assertSame('Same', $userData->get('shift_bezoeker', $account->id(), 'first_name'));
  }

}
