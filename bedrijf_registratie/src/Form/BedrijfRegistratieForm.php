<?php

namespace Drupal\bedrijf_registratie\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formulier voor de Bedrijf Registratie (België).
 */
class BedrijfRegistratieForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bedrijf_registratie_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $form['bedrijfsnaam'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bedrijfsnaam'),
      '#placeholder' => $this->t('Bijv. Mijn Bedrijf BV'),
      '#required' => TRUE,
    ];

    $form['ondernemingsnummer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ondernemingsnummer'),
      '#description' => $this->t('Formaat: BE 0XXX.XXX.XXX'),
      '#required' => TRUE,
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('E-mailadres'),
      '#description' => $this->t('Het officiële e-mailadres van het bedrijf.'),
      '#required' => TRUE,
    ];
    $form['telefoonnummer'] = [
      '#type' => 'tel',
      '#title' => $this->t('Telefoonnummer'),
      '#placeholder' => $this->t('+32 ...'),
    ];

    $form['maatschappelijke_zetel'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Maatschappelijke zetel'),
      '#placeholder' => $this->t('Straat en nummer, Postcode, Gemeente'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Bedrijf Registreren'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $naam = $form_state->getValue('bedrijfsnaam');
    
    \Drupal::messenger()->addStatus($this->t('Bedrijf "@naam" is succesvol aangemeld voor de Belgische markt.', [
      '@naam' => $naam,
    ]));
  }

}