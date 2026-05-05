<?php

namespace Drupal\shift_bezoeker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class RegistratieForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shift_bezoeker_registratie_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Koppelen aan de styling in je thema
    $form['#attached']['library'][] = 'shift_theme/global-styling';

    // 1. Keuze type registratie
    $form['registratie_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Registreer als:'),
      '#options' => [
        'bezoeker' => $this->t('Bezoeker'),
        'bedrijf' => $this->t('Bedrijf'),
      ],
      '#default_value' => 'bezoeker',
    ];

    // --- BEZOEKER WRAPPER ---
    $form['bezoeker_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="registratie_type"]' => ['value' => 'bezoeker'],
        ],
      ],
    ];

    $form['bezoeker_wrapper']['persoonlijk_header'] = [
      '#markup' => '<h2 class="form-section-title">' . $this->t('Persoonlijke Gegevens') . '</h2>',
    ];

    $form['bezoeker_wrapper']['name_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bezoeker_wrapper']['name_row']['firstName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bezoeker']]],
    ];

    $form['bezoeker_wrapper']['name_row']['lastName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bezoeker']]],
    ];

    $form['bezoeker_wrapper']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bezoeker']]],
    ];

    $form['bezoeker_wrapper']['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone (Optional)'),
    ];

    $form['bezoeker_wrapper']['event_header'] = [
      '#markup' => '<h2 class="form-section-title">' . $this->t('Event Details') . '</h2>',
    ];

    $form['bezoeker_wrapper']['event_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bezoeker_wrapper']['event_row']['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Rol'),
      '#options' => [
        'visitor' => $this->t('Bezoeker'),
        'speaker' => $this->t('Spreker'),
        'staff' => $this->t('Medewerker'),
      ],
      '#empty_option' => $this->t('Kies een rol...'),
    ];

    $form['bezoeker_wrapper']['gdpr_consent'] = [
       '#type' => 'checkbox',
       '#title' => $this->t('I agree to the GDPR terms'),
    ];

    // --- BEDRIJF WRAPPER ---
    $form['bedrijf_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="registratie_type"]' => ['value' => 'bedrijf'],
        ],
      ],
    ];

    $form['bedrijf_wrapper']['company_header'] = [
      '#markup' => '<h2 class="form-section-title">' . $this->t('Bedrijfsgegevens') . '</h2>',
    ];

    $form['bedrijf_wrapper']['company_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bedrijf_wrapper']['company_row']['companyName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company Name'),
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bedrijf']]],
    ];

    $form['bedrijf_wrapper']['company_row']['vatNumber'] = [
      '#type' => 'textfield',
      '#title' => $this->t('VAT Number'),
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bedrijf']]],
    ];

    $form['bedrijf_wrapper']['address_header'] = [
      '#markup' => '<h2 class="form-section-title">' . $this->t('Adresgegevens') . '</h2>',
    ];

    $form['bedrijf_wrapper']['address_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bedrijf_wrapper']['address_row']['street'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street'),
    ];

    $form['bedrijf_wrapper']['address_row']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
    ];

    // Submit knop
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Registreer!'),
      '#attributes' => ['class' => ['btn-primary']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $type = $values['registratie_type'];

    // 1. Validatie voor Bezoekers
    if ($type === 'bezoeker') {
      if (empty($values['firstName'])) {
        $form_state->setErrorByName('firstName', $this->t('Voornaam is verplicht.'));
      }
      if (empty($values['lastName'])) {
        $form_state->setErrorByName('lastName', $this->t('Achternaam is verplicht.'));
      }
      if (empty($values['email'])) {
        $form_state->setErrorByName('email', $this->t('E-mailadres is verplicht.'));
      }
    }

    // 2. Validatie voor Bedrijven
    if ($type === 'bedrijf') {
      if (empty($values['companyName'])) {
        $form_state->setErrorByName('companyName', $this->t('Bedrijfsnaam is verplicht.'));
      }
      if (empty($values['vatNumber'])) {
        $form_state->setErrorByName('vatNumber', $this->t('BTW-nummer is verplicht.'));
      }
      // Jouw bestaande BTW-check
      elseif (!str_starts_with(strtoupper($values['vatNumber']), 'BE')) {
        $form_state->setErrorByName('vatNumber', $this->t('Een Belgisch BTW-nummer moet beginnen met BE.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->addStatus($this->t('Bedankt! Je gegevens zijn verwerkt.'));
    $form_state->setRedirect('shift_bezoeker.sessions'); // Of je eigen succes pagina
  }
}