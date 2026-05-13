<?php

namespace Drupal\Session_Management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class SessionManagement extends ControllerBase {

  public function content() {
    $rows = [];
    try {
      $database = \Drupal::database();
      
      // Debug: Check table name and count.
      $tableName = $database->tablePrefix() . 'session';
      $count = $database->select('session', 's')->countQuery()->execute()->fetchField();
      $this->messenger()->addStatus($this->t('Debug: Querying table "@table". Found @count rows.', [
        '@table' => $tableName,
        '@count' => $count,
      ]));

      $query = $database->select('session', 's');
      $query->leftJoin('location', 'l', 's.location_id = l.location_id');
      $query->fields('s', ['session_id', 'title', 'date', 'start_time', 'end_time', 'capacity', 'status'])
        ->fields('l', ['room_name'])
        ->orderBy('s.date', 'ASC')
        ->orderBy('s.start_time', 'ASC');
      
      $results = $query->execute()->fetchAll();

      foreach ($results as $session) {
        $edit_url = Url::fromRoute('session_management.edit', ['sessionId' => $session->session_id]);
        
        $rows[] = [
          $session->title,
          $session->date . ' ' . $session->start_time,
          $session->end_time,
          $session->room_name ?: '-',
          '-',
          $session->capacity,
          [
            'data' => [
              '#type' => 'link',
              '#title' => $this->t('Edit'),
              '#url' => $edit_url,
              '#attributes' => ['class' => ['button', 'button--small']],
            ],
          ],
        ];
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Could not load sessions: @msg', ['@msg' => $e->getMessage()]));
    }

    $create_url = Url::fromRoute('session_management.create');

    return [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h1>Sessions</h1>',
      ],
      'create_button' => [
        '#type' => 'link',
        '#title' => $this->t('Create new session'),
        '#url' => $create_url,
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['Title', 'Start', 'End', 'Location', 'Speaker', 'Capacity', 'Actions'],
        '#rows' => $rows,
        '#empty' => $this->t('No sessions found in the database.'),
      ],
    ];
  }

}