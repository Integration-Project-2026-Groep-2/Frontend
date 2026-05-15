<?php

namespace Drupal\session_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class SessionManagement extends ControllerBase {

  public function content() {
    $rows = [];
    try {
      $database = \Drupal::database();
      
      $query = $database->select('session', 's');
      $query->leftJoin('location', 'l', 's.location_id = l.location_id');
      $query->leftJoin('session_speaker', 'ss', 's.session_id = ss.session_id');
      $query->fields('s', ['session_id', 'title', 'date', 'start_time', 'end_time', 'capacity', 'status'])
        ->fields('l', ['room_name'])
        ->fields('ss', ['speaker_id'])
        ->orderBy('s.date', 'ASC')
        ->orderBy('s.start_time', 'ASC');
      
      $results = $query->execute()->fetchAll();

      foreach ($results as $session) {
        $edit_url = Url::fromRoute('session_management.edit', ['sessionId' => $session->session_id]);
        
        $status_class = 'status-' . strtolower($session->status);
        $status_label = ucfirst($session->status);

        $speaker_name = '-';
        if (!empty($session->speaker_id)) {
          $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['uuid' => $session->speaker_id]);
          if ($users) {
            $user = reset($users);
            $firstName = $user->hasField('field_first_name') ? $user->get('field_first_name')->value : '';
            $lastName  = $user->hasField('field_surname')    ? $user->get('field_surname')->value    : '';
            $speaker_name = trim($firstName . ' ' . $lastName) ?: $user->getEmail();
          }
        }

        $rows[] = [
          'data' => [
            $session->title,
            $session->date . ' ' . $session->start_time,
            $session->end_time,
            $session->room_name ?: '-',
            $session->capacity,
            $speaker_name,
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
      $this->messenger()->addError($this->t('Could not load sessions: @err', ['@err' => $e->getMessage()]));
    }

    $header = [
      $this->t('Title'),
      $this->t('Start'),
      $this->t('End'),
      $this->t('Location'),
      $this->t('Capacity'),
      $this->t('Speaker'),
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
          '#markup' => '<h1>' . $this->t('Sessions') . '</h1>',
        ],
        'add_button' => [
          '#type' => 'link',
          '#title' => $this->t('Add Session'),
          '#url' => Url::fromRoute('session_management.create'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No sessions found in the database.'),
        '#attributes' => ['class' => ['session-management-table']],
      ],
    ];
  }

}