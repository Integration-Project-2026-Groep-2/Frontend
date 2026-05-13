<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Unit\Service;

use Drupal\ai_dashboard\Service\IncidentRepository;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ai_dashboard\Service\IncidentRepository
 * @group ai_dashboard
 */
class IncidentRepositoryShapersTest extends UnitTestCase {

  private function fakeEntity(array $fields, int $id): object {
    return new class($fields, $id) {
      public function __construct(private array $fields, private int $id) {}
      public function id(): int { return $this->id; }
      public function get(string $name): object {
        $value = $this->fields[$name] ?? NULL;
        return new class($value) {
          public function __construct(public mixed $value) {}
        };
      }
    };
  }

  public function testToListItemExtractsDiagnosedRootCause(): void {
    $entity = $this->fakeEntity([
      'event_type' => 'incident_diagnosed',
      'correlation_id' => 'cid-1',
      'service' => 'kassa',
      'severity' => 'critical',
      'confidence' => 'high',
      'received_at' => 1747000000,
      'payload_json' => json_encode([
        'payload' => ['diagnosis' => ['root_cause' => 'connection pool exhausted']],
      ]),
    ], 5);

    $item = IncidentRepository::toListItem($entity);

    $this->assertSame(5, $item['id']);
    $this->assertSame('incident_diagnosed', $item['event_type']);
    $this->assertSame('kassa', $item['service']);
    $this->assertSame('high', $item['confidence']);
    $this->assertSame(1747000000, $item['received_at']);
    $this->assertSame('connection pool exhausted', $item['root_cause_preview']);
  }

  public function testToListItemPrefixesSkipReason(): void {
    $entity = $this->fakeEntity([
      'event_type' => 'incident_skipped',
      'correlation_id' => 'cid-2',
      'service' => 'crm',
      'severity' => 'warning',
      'confidence' => NULL,
      'received_at' => 1747000010,
      'payload_json' => json_encode([
        'payload' => ['reason' => 'debounced within 60s window'],
      ]),
    ], 6);

    $item = IncidentRepository::toListItem($entity);

    $this->assertSame('skipped: debounced within 60s window', $item['root_cause_preview']);
  }

  public function testToListItemPrefixesResolvedWithOriginalSummary(): void {
    $entity = $this->fakeEntity([
      'event_type' => 'incident_resolved',
      'correlation_id' => 'cid-r1',
      'service' => 'kassa',
      'severity' => 'info',
      'confidence' => NULL,
      'received_at' => 1747000020,
      'payload_json' => json_encode([
        'payload' => ['original_summary' => 'KASSA heartbeat is back online'],
      ]),
    ], 7);

    $item = IncidentRepository::toListItem($entity);

    $this->assertSame('resolved: KASSA heartbeat is back online', $item['root_cause_preview']);
  }

  public function testToListItemExposesOriginalTsFromPayload(): void {
    $entity = $this->fakeEntity([
      'event_type' => 'incident_diagnosed',
      'correlation_id' => 'cid-ts-1',
      'service' => 'kassa',
      'severity' => 'critical',
      'confidence' => 'high',
      'received_at' => 1747000100,
      'payload_json' => json_encode([
        'payload' => ['original_timestamp' => '2026-05-12T14:23:17Z'],
      ]),
    ], 10);

    $item = IncidentRepository::toListItem($entity);

    $this->assertSame(strtotime('2026-05-12T14:23:17Z'), $item['original_ts']);
  }

  public function testToListItemReturnsNullOriginalTsWhenMissing(): void {
    $entity = $this->fakeEntity([
      'event_type' => 'incident_diagnosed',
      'correlation_id' => 'cid-ts-2',
      'service' => 'crm',
      'severity' => 'critical',
      'confidence' => NULL,
      'received_at' => 1747000110,
      'payload_json' => json_encode([
        'payload' => ['diagnosis' => ['root_cause' => 'x']],
      ]),
    ], 11);

    $item = IncidentRepository::toListItem($entity);

    $this->assertArrayHasKey('original_ts', $item);
    $this->assertNull($item['original_ts']);
  }

  public function testToListItemReturnsNullOriginalTsWhenUnparseable(): void {
    $entity = $this->fakeEntity([
      'event_type' => 'incident_resolved',
      'correlation_id' => 'cid-ts-3',
      'service' => 'kassa',
      'severity' => 'info',
      'confidence' => NULL,
      'received_at' => 1747000120,
      'payload_json' => json_encode([
        'payload' => ['original_timestamp' => 'not a date at all'],
      ]),
    ], 12);

    $item = IncidentRepository::toListItem($entity);

    $this->assertNull($item['original_ts']);
  }

  public function testToListItemTruncatesPreviewAt200Chars(): void {
    $longRootCause = str_repeat('x', 350);
    $entity = $this->fakeEntity([
      'event_type' => 'incident_diagnosed',
      'correlation_id' => 'cid-3',
      'service' => 'kassa',
      'severity' => 'critical',
      'confidence' => 'high',
      'received_at' => 1747000020,
      'payload_json' => json_encode([
        'payload' => ['diagnosis' => ['root_cause' => $longRootCause]],
      ]),
    ], 7);

    $item = IncidentRepository::toListItem($entity);

    $this->assertSame(200, mb_strlen($item['root_cause_preview']));
  }

  public function testToListItemHandlesEmptyPayloadJson(): void {
    $entity = $this->fakeEntity([
      'event_type' => 'incident_circuit_open',
      'correlation_id' => 'cid-4',
      'service' => 'kassa',
      'severity' => 'critical',
      'confidence' => NULL,
      'received_at' => 1747000030,
      'payload_json' => '',
    ], 8);

    $item = IncidentRepository::toListItem($entity);

    $this->assertSame('', $item['root_cause_preview']);
    $this->assertSame('incident_circuit_open', $item['event_type']);
  }

  public function testToDetailReturnsDecodedEnvelope(): void {
    $envelope = [
      'event' => 'incident_diagnosed',
      'payload' => [
        'diagnosis' => ['root_cause' => 'rc', 'confidence' => 'high'],
        'tool_trace' => [['server' => 'crm', 'tool' => 'fetch_logs', 'ms' => 12]],
      ],
    ];
    $entity = $this->fakeEntity([
      'event_type' => 'incident_diagnosed',
      'correlation_id' => 'cid-5',
      'service' => 'kassa',
      'severity' => 'critical',
      'confidence' => 'high',
      'received_at' => 1747000040,
      'processed_at' => 1747000041,
      'payload_json' => json_encode($envelope),
    ], 9);

    $detail = IncidentRepository::toDetail($entity);

    $this->assertSame(9, $detail['id']);
    $this->assertSame(1747000041, $detail['processed_at']);
    $this->assertSame($envelope, $detail['envelope']);
  }

  public function testToDetailReturnsNullEnvelopeWhenJsonInvalid(): void {
    $entity = $this->fakeEntity([
      'event_type' => 'incident_diagnosed',
      'correlation_id' => 'cid-6',
      'service' => 'kassa',
      'severity' => 'critical',
      'confidence' => 'high',
      'received_at' => 1747000050,
      'processed_at' => 1747000051,
      'payload_json' => '{ not valid json',
    ], 10);

    $detail = IncidentRepository::toDetail($entity);

    $this->assertNull($detail['envelope']);
  }

}
