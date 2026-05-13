<?php

namespace Drupal\hello_world\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class RegisterCompanyForm extends FormBase {

  public function getFormId(): string {
    return 'register_company_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('This is an application to create a company account. Once you submit, an admin will review your application and send any updates to the provided email address.') . '</p>',
    ];

    $form['contact_person'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact person information'),
      '#description' => $this->t('This person\'s info will be used to create the first company manager account. Login details will be sent via email once approved.'),
    ];

    $form['contact_person']['contact_first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact person first name'),
      '#required' => TRUE,
    ];

    $form['contact_person']['contact_last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact person last name'),
      '#required' => TRUE,
    ];

    $form['contact_person']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['contact_person']['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Contact person phone number'),
      '#required' => FALSE,
    ];

    $form['company'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Company information'),
    ];

    $form['company']['company_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company name'),
      '#required' => TRUE,
    ];

    $form['company']['vat_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('VAT Number'),
      '#required' => TRUE,
    ];

    $form['company']['street'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street'),
      '#required' => TRUE,
    ];

    $form['company']['house_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('House Number'),
      '#required' => TRUE,
    ];

    $form['company']['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#required' => TRUE,
    ];

    $form['company']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
    ];

    $form['company']['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#default_value' => 'Belgium',
      '#required' => TRUE,
    ];

    $form['gdpr_consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree to the GDPR terms'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit application'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $phone = (string) $form_state->getValue('phone');
    if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone)) < 10) {
      $form_state->setErrorByName('phone', $this->t('Phone number must be at least 10 digits.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $submission = $this->extractSubmissionValues($form_state);

    // TODO: Replace with persistent storage (entity/table) and email workflow.
    \Drupal::logger('hello_world')->notice(
      'Company application received for @company from @email.',
      [
        '@company' => (string) ($submission['company_name'] ?? ''),
        '@email' => (string) ($submission['email'] ?? ''),
      ]
    );

    $this->messenger()->addStatus(
      $this->t('Your application has been received. Email notifications will be added in a future update.')
    );
  }

  private function extractSubmissionValues(FormStateInterface $form_state): array {
    return [
      'contact_first_name' => $form_state->getValue('contact_first_name'),
      'contact_last_name' => $form_state->getValue('contact_last_name'),
      'email' => $form_state->getValue('email'),
      'phone' => $form_state->getValue('phone'),
      'company_name' => $form_state->getValue('company_name'),
      'vat_number' => $form_state->getValue('vat_number'),
      'street' => $form_state->getValue('street'),
      'house_number' => $form_state->getValue('house_number'),
      'postal_code' => $form_state->getValue('postal_code'),
      'city' => $form_state->getValue('city'),
      'country' => $form_state->getValue('country'),
      'gdpr_consent' => (int) $form_state->getValue('gdpr_consent'),
    ];
  }

}
