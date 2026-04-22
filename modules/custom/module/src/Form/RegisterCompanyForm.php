<?php

namespace Drupal\hello_world\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class RegisterCompanyForm extends FormBase {

  private const TEMPSTORE_COLLECTION = 'hello_world.company_application';
  private const TEMPSTORE_KEY = 'draft';

  public function getFormId(): string {
    return 'register_company_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $draft = $this->getDraft();

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
      '#default_value' => $draft['contact_first_name'] ?? '',
    ];

    $form['contact_person']['contact_last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact person last name'),
      '#required' => TRUE,
      '#default_value' => $draft['contact_last_name'] ?? '',
    ];

    $form['contact_person']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#default_value' => $draft['email'] ?? '',
    ];

    $form['contact_person']['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Contact person phone number'),
      '#required' => FALSE,
      '#default_value' => $draft['phone'] ?? '',
    ];

    $form['company'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Company information'),
    ];

    $form['company']['company_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company name'),
      '#required' => TRUE,
      '#default_value' => $draft['company_name'] ?? '',
    ];

    $form['company']['vat_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('VAT Number'),
      '#required' => TRUE,
      '#default_value' => $draft['vat_number'] ?? '',
    ];

    $form['company']['street'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street'),
      '#required' => TRUE,
      '#default_value' => $draft['street'] ?? '',
    ];

    $form['company']['house_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('House Number'),
      '#required' => TRUE,
      '#default_value' => $draft['house_number'] ?? '',
    ];

    $form['company']['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#required' => TRUE,
      '#default_value' => $draft['postal_code'] ?? '',
    ];

    $form['company']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
      '#default_value' => $draft['city'] ?? '',
    ];

    $form['company']['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#default_value' => $draft['country'] ?? 'Belgium',
      '#required' => TRUE,
    ];

    $form['gdpr_consent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree to the GDPR terms'),
      '#required' => TRUE,
      '#default_value' => !empty($draft['gdpr_consent']) ? 1 : 0,
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
    $submission = $this->extractDraftValues($form_state);

    // TODO: Replace with persistent storage (entity/table) and email workflow.
    \Drupal::logger('hello_world')->notice(
      'Company application received for @company from @email.',
      [
        '@company' => (string) ($submission['company_name'] ?? ''),
        '@email' => (string) ($submission['email'] ?? ''),
      ]
    );

    // Clear any previous draft now that the submission is completed.
    $this->clearDraft();

    $this->messenger()->addStatus(
      $this->t('Your application has been received. Email notifications will be added in a future update.')
    );
  }

  private function getDraft(): array {
    $draft = \Drupal::service('tempstore.private')
      ->get(self::TEMPSTORE_COLLECTION)
      ->get(self::TEMPSTORE_KEY);

    return is_array($draft) ? $draft : [];
    }

  private function saveDraft(array $draft): void {
    \Drupal::service('tempstore.private')
      ->get(self::TEMPSTORE_COLLECTION)
      ->set(self::TEMPSTORE_KEY, $draft);
  }

  private function clearDraft(): void {
    \Drupal::service('tempstore.private')
      ->get(self::TEMPSTORE_COLLECTION)
      ->delete(self::TEMPSTORE_KEY);
  }

  private function extractDraftValues(FormStateInterface $form_state): array {
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