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

    $rows = [];
    try {
      $database = \Drupal::database();
      
      $query = $database->select('location', 'l')
        ->fields('l', ['location_id', 'room_name', 'capacity', 'address', 'status'])
        ->orderBy('room_name', 'ASC');
      $results = $query->execute()->fetchAll();

      foreach ($results as $location) {
        $rows[] = [
          $location->room_name,
          $location->capacity,
          $location->address ?: '-',
          $location->status,
          [
            'data' => [
              '#type' => 'link',
              '#title' => $this->t('Edit'),
              '#url' => Url::fromRoute('session_management.location.edit', ['locationId' => $location->location_id]),
              '#attributes' => ['class' => ['button', 'button--small']],
            ],
          ],
        ];
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Could not load locations: @msg', ['@msg' => $e->getMessage()]));
    }

    return [
      '#type' => 'container',
      '#cache' => [
        'max-age' => 0,
      ],
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
        '#rows'  => $rows,
        '#empty' => $this->t('No locations found in the database.'),
      ],
    ];
  }

}
