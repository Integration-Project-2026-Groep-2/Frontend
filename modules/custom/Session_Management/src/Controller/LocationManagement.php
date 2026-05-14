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
        $edit_url = Url::fromRoute('session_management.location.edit', ['locationId' => $location->location_id]);
        
        $status_class = 'status-' . strtolower($location->status);
        $status_label = ucfirst($location->status);

        $rows[] = [
          'data' => [
            $location->room_name,
            $location->capacity,
            $location->address ?: '-',
            [
              'data' => [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#value' => $status_label,
                '#attributes' => ['class' => ['status-badge', $status_class]],
              ],
            ],
            [
              'data' => [
                '#type' => 'link',
                '#title' => $this->t('Edit'),
                '#url' => $edit_url,
                '#attributes' => ['class' => ['action-link', 'edit']],
              ],
            ],
          ],
        ];
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Could not load locations: @err', ['@err' => $e->getMessage()]));
    }

    $header = [
      $this->t('Room Name'),
      $this->t('Capacity'),
      $this->t('Address'),
      $this->t('Status'),
      $this->t('Actions'),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['session-management-container']],
      '#attached' => [
        'library' => ['session_management/admin-styles'],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
      'header' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['session-management-header']],
        'title' => [
          '#markup' => '<h1>' . $this->t('Locations') . '</h1>',
        ],
        'add_button' => [
          '#type' => 'link',
          '#title' => $this->t('Add Location'),
          '#url' => Url::fromRoute('session_management.location.create'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No locations found in the database.'),
        '#attributes' => ['class' => ['session-management-table']],
      ],
    ];
  }

}
