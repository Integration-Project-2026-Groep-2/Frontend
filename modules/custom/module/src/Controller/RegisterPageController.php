<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RegisterPageController extends ControllerBase {

  public function registrationChoice(): array|RedirectResponse {
    $type = \Drupal::request()->query->get('type');

    if ($type === 'visitor' || $type === 'company') {
      $route = $type === 'company'
        ? 'hello_world.register_company'
        : 'hello_world.register_visitor';

      return new RedirectResponse(Url::fromRoute($route)->toString());
    }

    return [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Choose registration type'),
      ],
      'visitor_link' => [
        '#type' => 'link',
        '#title' => $this->t('Visitor Registration'),
        '#url' => Url::fromRoute('hello_world.register_visitor'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'spacer' => [
        '#markup' => ' ',
      ],
      'company_link' => [
        '#type' => 'link',
        '#title' => $this->t('Company Application'),
        '#url' => Url::fromRoute('hello_world.register_company'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
    ];
  }

}