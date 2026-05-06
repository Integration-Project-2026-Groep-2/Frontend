<?php

namespace Drupal\bedrijf_registratie\Controller;

use Drupal\Core\Controller\ControllerBase;

class PublicController extends ControllerBase {
  public function homePagina() {
    return [
      '#type' => 'inline_template',
      '#template' => '<div class="hero-section"><h1>{{ title }}</h1><p>{{ subtitle }}</p></div>',
      '#context' => [
        'title' => $this->t('Shift Festival 2026'),
        'subtitle' => $this->t('Het event voor Multimedia & MCT.'),
      ],
    ];
  }

  public function programmaPagina() {
    return [
      '#markup' => '<h2>' . $this->t('Ontdek onze sessies') . '</h2><p>' . $this->t('Bekijk hier de line-up van sprekers.') . '</p>',
    ];
  }
}