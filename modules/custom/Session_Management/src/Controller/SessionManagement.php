<?php

namespace Drupal\Session_Management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class SessionManagement extends ControllerBase {

  public function content() {
    $rows = [];
    try {
      $database = \Drupal::database();
      
      // We join with Location to get the room name.
      // Note: Speaker joining is more complex if there are multiple speakers per session.
      // For now, we'll fetch basic session data and join Location.
      $query = $database->select('Session', 's');
      $query->leftJoin('Location', 'l', 's.locationId = l.locationId');
      $query->fields('s', ['sessionId', 'title', 'date', 'startTime', 'endTime', 'capacity', 'status'])
        ->fields('l', ['roomName'])
        ->orderBy('s.date', 'ASC')
        ->orderBy('s.startTime', 'ASC');
      
      $results = $query->execute()->fetchAll();

      foreach ($results as $session) {
        $edit_url = Url::fromRoute('session_management.edit', ['sessionId' => $session->sessionId]);
        
        $rows[] = [
          $session->title,
          $session->date . ' ' . $session->startTime,
          $session->endTime,
          $session->roomName ?: '-',
          '-', // Speaker column (TODO: join with Speaker table)
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