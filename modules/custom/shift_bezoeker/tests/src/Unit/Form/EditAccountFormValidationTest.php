<?php

namespace Drupal\Tests\shift_bezoeker\Unit\Form;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\shift_bezoeker\Form\EditAccountForm;
use Drupal\Tests\UnitTestCase;

class EditAccountFormValidationTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  public function testEmptyFirstNameTriggersError(): void {
    $form = new EditAccountForm();
    $form->setStringTranslation($this->getStringTranslationStub());

    $form_state = (new FormState())->setValues([
      'firstName' => '',
      'lastName'  => 'Doe',
    ]);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertArrayHasKey('firstName', $form_state->getErrors());
  }

  public function testEmptyLastNameTriggersError(): void {
    $form = new EditAccountForm();
    $form->setStringTranslation($this->getStringTranslationStub());

    $form_state = (new FormState())->setValues([
      'firstName' => 'John',
      'lastName'  => '',
    ]);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertArrayHasKey('lastName', $form_state->getErrors());
  }

  public function testWhitespaceOnlyIsTreatedAsEmpty(): void {
    $form = new EditAccountForm();
    $form->setStringTranslation($this->getStringTranslationStub());

    $form_state = (new FormState())->setValues([
      'firstName' => '   ',
      'lastName'  => 'Doe',
    ]);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertArrayHasKey('firstName', $form_state->getErrors());
  }

  public function testValidValuesPassValidation(): void {
    $form = new EditAccountForm();
    $form->setStringTranslation($this->getStringTranslationStub());

    $form_state = (new FormState())->setValues([
      'firstName' => 'John',
      'lastName'  => 'Doe',
    ]);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

}
