<?php

namespace Drupal\session_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for the location management overview page.
 */
class LocationManagement extends ControllerBase {

  public function content(): array {
    $create_url = Url::fromRoute('session_management.location.create');

    return [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h1>' . $this->t('Locations') . '</h1>',
      ],
      'create_button' => [
        '#type'       => 'link',
        '#title'      => $this->t('Create new location'),
        '#url'        => $create_url,
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
      'table' => [
        '#type'   => 'table',
        '#header' => [
          $this->t('Room Name'),
          $this->t('Capacity'),
          $this->t('Address'),
          $this->t('Status'),
          $this->t('Actions'),
        ],
        '#rows'  => [],
        '#empty' => $this->t('No locations loaded yet. Locations are managed by the Planning system.'),
      ],
      'note' => [
        '#markup' => '<p>' . $this->t('Location data is retrieved from the Planning system via RabbitMQ.') . '</p>',
      ],
    ];
  }

}
