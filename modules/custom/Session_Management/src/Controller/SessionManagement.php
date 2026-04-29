<?php

namespace Drupal\Session_Management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class SessionManagement extends ControllerBase {

  public function createPage() {
    #mock data
    $sessions = [
      [
        'id' => '1',
        'title' => 'PHP Security Basics',
        'start_time' => '2026-05-10 14:00',
        'end_time' => '2026-05-10 14:45',
        'location' => 'Room A',
        'speaker' => 'Acme Corp',
        'capacity' => 50,
      ],
      [
        'id' => '2',
        'title' => 'Web Performance Tips',
        'start_time' => '2026-05-10 15:00',
        'end_time' => '2026-05-10 15:45',
        'location' => 'Room B',
        'speaker' => 'Tech Solutions',
        'capacity' => 30,
      ],
    ];

    $rows = [];
    foreach ($sessions as $session) {
      $edit_url = Url::fromRoute('session_management.edit', [
        'id' => $session['id'],
      ]);

      $rows[] = [
        $session['title'],
        $session['start_time'],
        $session['end_time'],
        $session['location'],
        $session['speaker'],
        $session['capacity'],
        Link::fromTextAndUrl($this->t('Edit session'), $edit_url)->toString(),
      ];
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
        '#header' => ['Title', 'Start', 'End', 'Location', 'Speaker', 'Capacity''Actions'],
        '#rows' => $rows,
        '#empty' => $this->t('No sessions found.'),
      ],
    ];
  }

}