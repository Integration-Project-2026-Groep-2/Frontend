<?php

declare(strict_types=1);

namespace Drupal\Tests\shift_bezoeker\Unit\Form;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\shift_bezoeker\Form\RegistratieForm;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for pure validation rules in RegistratieForm.
 *
 * Email-uniqueness check is mocked here; the Kernel test covers the real
 * entityTypeManager interaction. These tests focus on the rules that don't
 * need database state: required fields, password length, VAT prefix.
 *
 * @coversDefaultClass \Drupal\shift_bezoeker\Form\RegistratieForm
 * @group shift_bezoeker
 */
class RegistratieFormValidationTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    // Drupal::entityTypeManager() lookup fallback when emailExists() runs.
    // Mock returns empty so the email-uniqueness rule never fires here.
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadByProperties')->willReturn([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('user')->willReturn($userStorage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entityTypeManager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  private function makeForm(): RegistratieForm {
    $form = new RegistratieForm();
    $form->setStringTranslation($this->getStringTranslationStub());
    return $form;
  }

  private function validate(array $values): array {
    $form_state = new FormState();
    $form_state->clearErrors();
    $form_state->setValues($values);
    $form = $this->makeForm();
    $form_array = [];
    $form->validateForm($form_array, $form_state);
    $errors = [];
    foreach ($form_state->getErrors() as $field => $message) {
      $errors[$field] = (string) $message;
    }
    return $errors;
  }

  public function testPasswordTooShortReturnsError(): void {
    $errors = $this->validate([
      'registratie_type' => 'bezoeker',
      'email' => 'short@example.com',
      'pass' => '1234567',
      'firstName' => 'Test',
      'lastName' => 'User',
    ]);
    $this->assertArrayHasKey('pass', $errors);
    $this->assertStringContainsString('8 karakters', $errors['pass']);
  }

  public function testPasswordExactlyEightCharsPassesLengthCheck(): void {
    $errors = $this->validate([
      'registratie_type' => 'bezoeker',
      'email' => 'eight@example.com',
      'pass' => '12345678',
      'firstName' => 'Test',
      'lastName' => 'User',
    ]);
    $this->assertArrayNotHasKey('pass', $errors);
  }

  public function testBezoekerMissingFirstNameReturnsError(): void {
    $errors = $this->validate([
      'registratie_type' => 'bezoeker',
      'email' => 'noname@example.com',
      'pass' => 'longenough123',
      'firstName' => '',
      'lastName' => 'Doe',
    ]);
    $this->assertArrayHasKey('firstName', $errors);
  }

  public function testBezoekerMissingLastNameReturnsError(): void {
    $errors = $this->validate([
      'registratie_type' => 'bezoeker',
      'email' => 'noname@example.com',
      'pass' => 'longenough123',
      'firstName' => 'John',
      'lastName' => '',
    ]);
    $this->assertArrayHasKey('lastName', $errors);
  }

  public function testBedrijfMissingCompanyNameReturnsError(): void {
    $errors = $this->validate([
      'registratie_type' => 'bedrijf',
      'email' => 'co@example.com',
      'pass' => 'longenough123',
      'firstName' => 'Lars',
      'lastName' => 'Cowe',
      'companyName' => '',
      'vatNumber' => 'BE0123456789',
    ]);
    $this->assertArrayHasKey('companyName', $errors);
  }

  public function testBedrijfNonBelgianVatReturnsError(): void {
    $errors = $this->validate([
      'registratie_type' => 'bedrijf',
      'email' => 'co@example.com',
      'pass' => 'longenough123',
      'firstName' => 'Lars',
      'lastName' => 'Cowe',
      'companyName' => 'Acme NV',
      'vatNumber' => 'NL0123456789',
    ]);
    $this->assertArrayHasKey('vatNumber', $errors);
    $this->assertStringContainsString('BE', $errors['vatNumber']);
  }

  public function testBedrijfBelgianVatLowercaseStillAccepted(): void {
    // strtoupper() in validateForm makes the BE-prefix check
    // case-insensitive, so 'be0123' should pass.
    $errors = $this->validate([
      'registratie_type' => 'bedrijf',
      'email' => 'co@example.com',
      'pass' => 'longenough123',
      'firstName' => 'Lars',
      'lastName' => 'Cowe',
      'companyName' => 'Acme NV',
      'vatNumber' => 'be0123456789',
    ]);
    $this->assertArrayNotHasKey('vatNumber', $errors);
  }

  public function testBedrijfMissingContactPersonNameReturnsError(): void {
    /* C1 (Registration) requires firstName + lastName for both bezoeker and
     * bedrijf — bedrijf-flow needs a contact-persoon, not just companyName. */
    $errors = $this->validate([
      'registratie_type' => 'bedrijf',
      'email' => 'co@example.com',
      'pass' => 'longenough123',
      'firstName' => '',
      'lastName' => '',
      'companyName' => 'Acme NV',
      'vatNumber' => 'BE0123456789',
    ]);
    $this->assertArrayHasKey('firstName', $errors);
    $this->assertArrayHasKey('lastName', $errors);
  }

  public function testEmailEmptyReturnsError(): void {
    $errors = $this->validate([
      'registratie_type' => 'bezoeker',
      'email' => '   ',
      'pass' => 'longenough123',
      'firstName' => 'A',
      'lastName' => 'B',
    ]);
    $this->assertArrayHasKey('email', $errors);
  }

  /**
   * Reusable string-translation stub matching Drupal\Tests\UnitTestCase
   * convention — getStringTranslationStub is provided by the parent class.
   */

}
