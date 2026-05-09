<?php

declare(strict_types=1);

namespace Drupal\Tests\shift_bezoeker\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\shift_bezoeker\Form\RegistratieForm;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Kernel test for RegistratieForm — exercises real entity creation +
 * role assignment + UserData persistence against a Drupal kernel.
 *
 * @coversDefaultClass \Drupal\shift_bezoeker\Form\RegistratieForm
 * @group shift_bezoeker
 */
class RegistratieFormTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'shift_bezoeker',
  ];

  protected function setUp(): void {
    parent::setUp();
    // Bypass AMQP publish in kernel tests — RabbitMQClient retries 10x5s
    // on connect failure and submitForm calls publishRegistrationEvents
    // after user_login_finalize. Keeps the suite fast + isolated from broker.
    $_ENV['SHIFT_BEZOEKER_DISABLE_AMQP'] = '1';
    putenv('SHIFT_BEZOEKER_DISABLE_AMQP=1');

    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['user']);

    foreach (['visitor', 'speaker', 'kassa', 'company'] as $role_id) {
      Role::create(['id' => $role_id, 'label' => ucfirst($role_id)])->save();
    }

    User::create([
      'uid' => 0,
      'name' => '',
      'status' => 0,
    ])->save();
    User::create([
      'uid' => 1,
      'name' => 'admin',
      'mail' => 'admin@local.test',
      'status' => 1,
    ])->save();
  }

  private function submit(array $values): FormState {
    $form_state = new FormState();
    $form_state->clearErrors();
    $form_state->setValues($values);
    $form = RegistratieForm::create($this->container);
    $form_array = [];
    $form->submitForm($form_array, $form_state);
    return $form_state;
  }

  public function testBezoekerSubmitCreatesUserWithVisitorRole(): void {
    $email = 'visitor1@local.test';
    $this->submit([
      'registratie_type' => 'bezoeker',
      'email' => $email,
      'pass' => 'goodpass123',
      'firstName' => 'Visitor',
      'lastName' => 'One',
      'phone' => '0470000001',
      'role' => 'visitor',
      'gdpr_consent' => 1,
    ]);

    $accounts = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    $this->assertCount(1, $accounts);
    $account = reset($accounts);
    $this->assertSame($email, $account->getAccountName());
    $this->assertTrue($account->isActive());
    $this->assertContains('visitor', $account->getRoles(TRUE));
  }

  public function testBezoekerWithSpeakerRoleAssignedSpeakerRole(): void {
    $email = 'speaker@local.test';
    $this->submit([
      'registratie_type' => 'bezoeker',
      'email' => $email,
      'pass' => 'goodpass123',
      'firstName' => 'Spr',
      'lastName' => 'Eker',
      'role' => 'speaker',
      'gdpr_consent' => 1,
    ]);

    $accounts = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    $account = reset($accounts);
    $this->assertContains('speaker', $account->getRoles(TRUE));
  }

  public function testBedrijfSubmitCreatesUserWithCompanyRole(): void {
    $email = 'co@local.test';
    $this->submit([
      'registratie_type' => 'bedrijf',
      'email' => $email,
      'pass' => 'goodpass123',
      'firstName' => 'Lars',
      'lastName' => 'Cowe',
      'companyName' => 'Acme NV',
      'vatNumber' => 'BE0123456789',
      'street' => 'Stationsstraat 1',
      'city' => 'Brussel',
      'gdpr_consent' => 1,
    ]);

    $accounts = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    $account = reset($accounts);
    $this->assertContains('company', $account->getRoles(TRUE));
  }

  public function testBezoekerUserDataPersisted(): void {
    $email = 'persist@local.test';
    $this->submit([
      'registratie_type' => 'bezoeker',
      'email' => $email,
      'pass' => 'goodpass123',
      'firstName' => 'Per',
      'lastName' => 'Sist',
      'phone' => '0470001234',
      'role' => 'visitor',
      'gdpr_consent' => 1,
    ]);

    $accounts = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    $account = reset($accounts);
    $userData = $this->container->get('user.data');
    $this->assertSame('Per', $userData->get('shift_bezoeker', $account->id(), 'first_name'));
    $this->assertSame('Sist', $userData->get('shift_bezoeker', $account->id(), 'last_name'));
    $this->assertSame('0470001234', $userData->get('shift_bezoeker', $account->id(), 'phone'));
    $this->assertSame(1, (int) $userData->get('shift_bezoeker', $account->id(), 'gdpr_consent'));
  }

  public function testBedrijfUserDataPersistedAndVatUppercased(): void {
    $email = 'codata@local.test';
    $this->submit([
      'registratie_type' => 'bedrijf',
      'email' => $email,
      'pass' => 'goodpass123',
      'firstName' => 'Lars',
      'lastName' => 'Cowe',
      'phone' => '+32470000001',
      'companyName' => 'Acme BV',
      'vatNumber' => 'be0123456789',
      'street' => 'Stationsstraat 1',
      'city' => 'Brussel',
      'gdpr_consent' => 1,
    ]);

    $accounts = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    $account = reset($accounts);
    $userData = $this->container->get('user.data');
    // Persoon-velden moeten ALSO persisted zijn voor bedrijf (was eerder
    // alleen bezoeker — bug 2026-05-09: bedrijf-Contact kreeg companyName
    // als firstName).
    $this->assertSame('Lars', $userData->get('shift_bezoeker', $account->id(), 'first_name'));
    $this->assertSame('Cowe', $userData->get('shift_bezoeker', $account->id(), 'last_name'));
    $this->assertSame('+32470000001', $userData->get('shift_bezoeker', $account->id(), 'phone'));
    $this->assertSame('Acme BV', $userData->get('shift_bezoeker', $account->id(), 'company_name'));
    $this->assertSame('BE0123456789', $userData->get('shift_bezoeker', $account->id(), 'vat_number'));
    $this->assertSame('Stationsstraat 1', $userData->get('shift_bezoeker', $account->id(), 'street'));
    $this->assertSame('Brussel', $userData->get('shift_bezoeker', $account->id(), 'city'));
  }

  public function testEmailUniquenessValidationDetectsExistingEmail(): void {
    User::create([
      'name' => 'taken@local.test',
      'mail' => 'taken@local.test',
      'pass' => 'whatever1234',
      'status' => 1,
    ])->save();

    $form_state = new FormState();
    $form_state->clearErrors();
    $form_state->setValues([
      'registratie_type' => 'bezoeker',
      'email' => 'taken@local.test',
      'pass' => 'goodpass123',
      'firstName' => 'X',
      'lastName' => 'Y',
    ]);
    $form = RegistratieForm::create($this->container);
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('email', $errors);
  }

  public function testPasswordHashedNotStoredPlain(): void {
    $email = 'hashtest@local.test';
    $this->submit([
      'registratie_type' => 'bezoeker',
      'email' => $email,
      'pass' => 'plaintext1234',
      'firstName' => 'H',
      'lastName' => 'T',
      'role' => 'visitor',
      'gdpr_consent' => 1,
    ]);
    $accounts = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    $account = reset($accounts);
    // Password must be stored as a hash (Drupal's PasswordHasher prefixes
    // with $S$, $2y$, or similar). Any of those is fine — what matters is
    // that the plaintext doesn't survive.
    $stored = $account->getPassword();
    $this->assertNotSame('plaintext1234', $stored);
    $this->assertNotEmpty($stored);
  }
}
