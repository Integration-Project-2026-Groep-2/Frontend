<?php

namespace Drupal\bedrijf_registratie\Controller;

use Drupal\Core\Controller\ControllerBase;

class PublicController extends ControllerBase {
  public function homePagina() {
    return [
      '#theme' => 'home_page', 
      '#markup' => '<div class="hero-section"><h1>Shift Festival <span class="highlight">2026</span></h1><p>Hét event voor Multimedia & MCT.</p></div>',
    ];
  }

  public function programmaPagina() {
    return [
      '#markup' => '<h2>' . $this->t('Ontdek onze sessies') . '</h2><p>' . $this->t('Bekijk hier de line-up van sprekers.') . '</p>',
    ];
  }
}