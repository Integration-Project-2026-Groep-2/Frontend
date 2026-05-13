<?php

declare(strict_types=1);

namespace Drupal\ai_dashboard\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class IncidentRepository {

  private EntityStorageInterface $storage;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->storage = $entityTypeManager->getStorage('ai_incident');
  }

  public function recent(int $limit = 50, int $sinceTs = 0): array {
    $query = $this->storage->getQuery()->accessCheck(FALSE);
    if ($sinceTs > 0) {
      $query->condition('received_at', $sinceTs, '>');
    }
    $query->sort('received_at', 'DESC')->range(0, max(1, min($limit, 200)));
    $ids = $query->execute();
    if (empty($ids)) {
      return [];
    }
    return $this->storage->loadMultiple($ids);
  }

  public function load(int $id): ?object {
    return $this->storage->load($id);
  }

  public static function toListItem(object $entity): array {
    $payload = json_decode($entity->get('payload_json')->value ?? '', TRUE);
    $rootCause = '';
    if (is_array($payload) && isset($payload['payload']['diagnosis']['root_cause'])) {
      $rootCause = (string) $payload['payload']['diagnosis']['root_cause'];
    }
    elseif (is_array($payload) && isset($payload['payload']['reason'])) {
      $rootCause = 'skipped: ' . (string) $payload['payload']['reason'];
    }
    elseif (is_array($payload) && isset($payload['payload']['original_summary'])) {
      $rootCause = 'resolved: ' . (string) $payload['payload']['original_summary'];
    }

    $originalTs = NULL;
    if (is_array($payload) && isset($payload['payload']['original_timestamp'])) {
      $parsed = strtotime((string) $payload['payload']['original_timestamp']);
      if ($parsed !== FALSE) {
        $originalTs = $parsed;
      }
    }

    return [
      'id' => (int) $entity->id(),
      'event_type' => (string) $entity->get('event_type')->value,
      'correlation_id' => (string) $entity->get('correlation_id')->value,
      'service' => (string) $entity->get('service')->value,
      'severity' => (string) $entity->get('severity')->value,
      'confidence' => $entity->get('confidence')->value,
      'received_at' => (int) $entity->get('received_at')->value,
      'original_ts' => $originalTs,
      'root_cause_preview' => mb_substr($rootCause, 0, 200),
    ];
  }

  public static function toDetail(object $entity): array {
    $payload = json_decode($entity->get('payload_json')->value ?? '', TRUE);
    return [
      'id' => (int) $entity->id(),
      'event_type' => (string) $entity->get('event_type')->value,
      'correlation_id' => (string) $entity->get('correlation_id')->value,
      'service' => (string) $entity->get('service')->value,
      'severity' => (string) $entity->get('severity')->value,
      'confidence' => $entity->get('confidence')->value,
      'received_at' => (int) $entity->get('received_at')->value,
      'processed_at' => (int) $entity->get('processed_at')->value,
      'envelope' => is_array($payload) ? $payload : NULL,
    ];
  }

}
