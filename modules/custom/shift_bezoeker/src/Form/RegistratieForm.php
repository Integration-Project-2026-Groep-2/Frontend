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
      '#title' => 'Registreer als:',
      '#options' => [
        'bezoeker' => 'Bezoeker',
        'bedrijf' => 'Bedrijf',
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
      '#markup' => '<h2 class="form-section-title">Persoonlijke Gegevens</h2>',
    ];

    $form['bezoeker_wrapper']['name_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bezoeker_wrapper']['name_row']['firstName'] = [
      '#type' => 'textfield',
      '#title' => 'First Name',
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bezoeker']]],
    ];

    $form['bezoeker_wrapper']['name_row']['lastName'] = [
      '#type' => 'textfield',
      '#title' => 'Last Name',
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bezoeker']]],
    ];

    $form['bezoeker_wrapper']['email'] = [
      '#type' => 'email',
      '#title' => 'Email Address',
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bezoeker']]],
    ];

    $form['bezoeker_wrapper']['phone'] = [
      '#type' => 'tel',
      '#title' => 'Phone (Optional)',
    ];

    $form['bezoeker_wrapper']['event_header'] = [
      '#markup' => '<h2 class="form-section-title">Event Details</h2>',
    ];

    $form['bezoeker_wrapper']['event_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bezoeker_wrapper']['event_row']['role'] = [
      '#type' => 'select',
      '#title' => 'Rol',
      '#options' => [
        'visitor' => 'Bezoeker',
        'speaker' => 'Spreker',
        'staff' => 'Medewerker',
      ],
      '#empty_option' => 'Kies een rol...',
    ];

    $form['bezoeker_wrapper']['gdprConsent'] = [
       '#type' => 'checkbox',
       '#title' => 'I agree to the GDPR terms',
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
      '#markup' => '<h2 class="form-section-title">Bedrijfsgegevens</h2>',
    ];

    $form['bedrijf_wrapper']['company_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bedrijf_wrapper']['company_row']['companyName'] = [
      '#type' => 'textfield',
      '#title' => 'Company Name',
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bedrijf']]],
    ];

    $form['bedrijf_wrapper']['company_row']['vatNumber'] = [
      '#type' => 'textfield',
      '#title' => 'VAT Number',
      '#states' => ['required' => [':input[name="registratie_type"]' => ['value' => 'bedrijf']]],
    ];

    $form['bedrijf_wrapper']['address_header'] = [
      '#markup' => '<h2 class="form-section-title">Adresgegevens</h2>',
    ];

    $form['bedrijf_wrapper']['address_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row']],
    ];

    $form['bedrijf_wrapper']['address_row']['street'] = [
      '#type' => 'textfield',
      '#title' => 'Street',
    ];

    $form['bedrijf_wrapper']['address_row']['city'] = [
      '#type' => 'textfield',
      '#title' => 'City',
    ];

    // Submit knop
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Registreer!',
      '#attributes' => ['class' => ['btn-primary']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if ($values['registratie_type'] == 'bedrijf') {
      if (!empty($values['vatNumber']) && !str_starts_with(strtoupper($values['vatNumber']), 'BE')) {
        $form_state->setErrorByName('vatNumber', 'Een Belgisch BTW-nummer moet beginnen met BE.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->addStatus('Bedankt! Je gegevens zijn verwerkt.');
    $form_state->setRedirect('shift_bezoeker.sessions'); // Of je eigen succes pagina
  }
}