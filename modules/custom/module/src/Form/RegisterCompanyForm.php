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
      '#required' => FALSE,
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
      '#default_value' => 'NL',
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
    $admin_to = \Drupal::config('system.site')->get('mail');

    $body = [
      'A new company application has been submitted.',
      '',
      'Contact person:',
      'First name: ' . $form_state->getValue('contact_first_name'),
      'Last name: ' . $form_state->getValue('contact_last_name'),
      'Email: ' . $form_state->getValue('email'),
      'Phone: ' . ($form_state->getValue('phone') ?: '-'),
      '',
      'Company information:',
      'Company name: ' . $form_state->getValue('company_name'),
      'VAT number: ' . ($form_state->getValue('vat_number') ?: '-'),
      'Street: ' . $form_state->getValue('street'),
      'House number: ' . $form_state->getValue('house_number'),
      'Postal code: ' . $form_state->getValue('postal_code'),
      'City: ' . $form_state->getValue('city'),
      'Country: ' . $form_state->getValue('country'),
      '',
      'GDPR consent: Yes',
    ];

    $params = [
      'subject' => 'New company application: ' . $form_state->getValue('company_name'),
      'body' => $body,
      'reply_to' => $form_state->getValue('email'),
    ];

    $langcode = \Drupal::service('language_manager')->getDefaultLanguage()->getId();

    $result = \Drupal::service('plugin.manager.mail')->mail(
      'hello_world',
      'company_application',
      $admin_to,
      $langcode,
      $params,
      NULL,
      TRUE
    );

    if (!empty($result['result'])) {
      $this->messenger()->addStatus($this->t('Your application has been sent successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Your application could not be sent. Please try again later.'));
    }
  }

}