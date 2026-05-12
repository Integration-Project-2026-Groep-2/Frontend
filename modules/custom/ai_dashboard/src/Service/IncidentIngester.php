<?php

declare(strict_types=1);

namespace Drupal\ai_dashboard\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

class IncidentIngester {

  private LoggerInterface $logger;
  private EntityStorageInterface $storage;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $logChannelFactory,
  ) {
    $this->storage = $entityTypeManager->getStorage('ai_incident');
    $this->logger = $logChannelFactory->get('ai_dashboard');
  }

  public function save(array $envelope): bool {
    if (!isset($envelope['event']) || !is_string($envelope['event'])) {
      throw new \InvalidArgumentException('envelope missing event field');
    }
    if (!isset($envelope['payload']) || !is_array($envelope['payload'])) {
      throw new \InvalidArgumentException('envelope missing payload object');
    }

    $eventType = $envelope['event'];
    $payload = $envelope['payload'];
    $correlationId = (string) ($payload['correlation_id'] ?? '');

    if ($correlationId !== '' && $this->alreadyIngested($correlationId, $eventType)) {
      return FALSE;
    }

    $receivedAt = isset($envelope['timestamp']) && is_string($envelope['timestamp'])
      ? (strtotime($envelope['timestamp']) ?: time())
      : time();

    $entity = $this->storage->create([
      'event_type' => $eventType,
      'correlation_id' => $correlationId,
      'service' => (string) ($payload['service'] ?? 'unknown'),
      'severity' => (string) ($payload['severity'] ?? 'unknown'),
      'confidence' => $this->extractConfidence($payload),
      'received_at' => $receivedAt,
      'processed_at' => time(),
      'payload_json' => json_encode($envelope, JSON_UNESCAPED_SLASHES),
    ]);
    $entity->save();

    $this->logger->info('ingested @event for @service (correlation=@cid)', [
      '@event' => $eventType,
      '@service' => $payload['service'] ?? 'unknown',
      '@cid' => $correlationId,
    ]);
    return TRUE;
  }

  private function alreadyIngested(string $correlationId, string $eventType): bool {
    $existing = $this->storage->loadByProperties([
      'correlation_id' => $correlationId,
      'event_type' => $eventType,
    ]);
    return !empty($existing);
  }

  private function extractConfidence(array $payload): ?string {
    if (!isset($payload['diagnosis']) || !is_array($payload['diagnosis'])) {
      return NULL;
    }
    $value = $payload['diagnosis']['confidence'] ?? NULL;
    return is_string($value) ? $value : NULL;
  }

}
