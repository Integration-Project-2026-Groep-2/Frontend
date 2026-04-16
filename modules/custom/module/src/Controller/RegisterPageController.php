<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RegisterPageController extends ControllerBase {

  public function registerVisitor(): array|RedirectResponse {
    $type = \Drupal::request()->query->get('type');

    if ($type === 'visitor' || $type === 'company') {
      $route = $type === 'company'
        ? 'hello_world.register_company'
        : 'hello_world.register_visitor';

      return new RedirectResponse(Url::fromRoute($route)->toString());
    }

    return [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->t('Choose registration type') . '</h2>
        <p>
          <a class="button button--primary" href="/register-visitor">' . $this->t('Visitor Registration') . '</a>
          <a class="button button--primary" href="/register-company">' . $this->t('Company Application') . '</a>
        </p>',
    ];
  }

}