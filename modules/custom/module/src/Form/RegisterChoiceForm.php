<?php

namespace Drupal\hello_world\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class RegisterChoiceForm extends FormBase {

  public function getFormId(): string {
    return 'register_choice_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['choice'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose registration type'),
      '#options' => [
        'visitor' => $this->t('Visitor'),
        'company' => $this->t('Company'),
      ],
      '#default_value' => $form_state->getValue('choice') ?: '',
      '#ajax' => [
        'callback' => '::ajaxChoiceCallback',
        'event' => 'change',
        'wrapper' => 'registration-choice-wrapper',
      ],
    ];

    $form['dynamic'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'registration-choice-wrapper'],
    ];

    $choice = $form_state->getValue('choice');

    if ($choice === 'visitor') {
      $form['dynamic']['content'] = [
        '#type' => 'link',
        '#title' => $this->t('Go to visitor registration'),
        '#url' => Url::fromRoute('hello_world.register_visitor'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }
    elseif ($choice === 'company') {
      $form['dynamic']['content'] = [
        '#type' => 'link',
        '#title' => $this->t('Go to company application'),
        '#url' => Url::fromRoute('hello_world.register_company'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }

    return $form;
  }

  public function ajaxChoiceCallback(array &$form, FormStateInterface $form_state) {
    return $form['dynamic'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}