<?php

namespace Drupal\bedrijf_registratie\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formulier om een medewerker toe te voegen aan een bedrijf.
 */
class MedewerkerToevoegenForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'medewerker_toevoegen_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $form['voornaam'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Voornaam'),
      '#required' => TRUE,
    ];

    $form['achternaam'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Achternaam'),
      '#required' => TRUE,
    ];

    $form['functie'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Functie'),
      '#placeholder' => $this->t('Bijv. Projectmanager'),
    ];

    $form['email_medewerker'] = [
      '#type' => 'email',
      '#title' => $this->t('E-mailadres medewerker'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Medewerker Toevoegen'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $voornaam = $form_state->getValue('voornaam');
    \Drupal::messenger()->addStatus($this->t('Medewerker @naam is succesvol toegevoegd.', [
      '@naam' => $voornaam,
    ]));
    
    // Na toevoegen terug naar het overzicht
    $form_state->setRedirect('bedrijf_registratie.gegevens');
  }

}