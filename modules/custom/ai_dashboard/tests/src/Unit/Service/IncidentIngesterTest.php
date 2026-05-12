<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Unit\Service;

use Drupal\ai_dashboard\Service\IncidentIngester;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\ai_dashboard\Service\IncidentIngester
 */
class IncidentIngesterTest extends UnitTestCase {

  use ProphecyTrait;

  private $storage;
  private $logger;
  private $entityTypeManager;
  private $logChannelFactory;

  protected function setUp(): void {
    parent::setUp();
    $this->storage = $this->prophesize(EntityStorageInterface::class);
    $this->logger = $this->prophesize(LoggerChannelInterface::class);

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityTypeManager->getStorage('ai_incident')->willReturn($this->storage->reveal());

    $this->logChannelFactory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $this->logChannelFactory->get('ai_dashboard')->willReturn($this->logger->reveal());
  }

  private function ingester(): IncidentIngester {
    return new IncidentIngester(
      $this->entityTypeManager->reveal(),
      $this->logChannelFactory->reveal(),
    );
  }

  public function testRejectsEnvelopeWithoutEvent(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->ingester()->save(['payload' => ['service' => 'kassa']]);
  }

  public function testRejectsEnvelopeWithoutPayload(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->ingester()->save(['event' => 'incident_diagnosed']);
  }

  public function testIngestsValidEnvelopeAndCreatesEntity(): void {
    $this->storage->loadByProperties(Argument::any())->willReturn([]);

    $entity = $this->prophesize(EntityInterface::class);
    $entity->save()->shouldBeCalled();

    $this->storage->create(Argument::that(function ($values) {
      return $values['event_type'] === 'incident_diagnosed'
        && $values['service'] === 'kassa'
        && $values['severity'] === 'critical'
        && $values['confidence'] === 'high'
        && $values['correlation_id'] === 'cid-1';
    }))->willReturn($entity->reveal());

    $envelope = [
      'event' => 'incident_diagnosed',
      'source' => 'mcp-master',
      'timestamp' => '2026-05-10T14:23:42Z',
      'payload' => [
        'correlation_id' => 'cid-1',
        'service' => 'kassa',
        'severity' => 'critical',
        'diagnosis' => ['confidence' => 'high'],
      ],
    ];

    $this->assertTrue($this->ingester()->save($envelope));
  }

  public function testSkipsDuplicateCorrelationIdAndEventType(): void {
    $existing = $this->prophesize(EntityInterface::class);
    $this->storage->loadByProperties([
      'correlation_id' => 'cid-1',
      'event_type' => 'incident_diagnosed',
    ])->willReturn(['1' => $existing->reveal()]);

    $this->storage->create(Argument::any())->shouldNotBeCalled();

    $envelope = [
      'event' => 'incident_diagnosed',
      'payload' => ['correlation_id' => 'cid-1', 'service' => 'kassa', 'severity' => 'critical'],
    ];
    $this->assertFalse($this->ingester()->save($envelope));
  }

  public function testHandlesMissingDiagnosisConfidenceAsNull(): void {
    $this->storage->loadByProperties(Argument::any())->willReturn([]);

    $entity = $this->prophesize(EntityInterface::class);
    $entity->save()->shouldBeCalled();

    $this->storage->create(Argument::that(function ($values) {
      return $values['event_type'] === 'incident_skipped'
        && $values['confidence'] === NULL;
    }))->willReturn($entity->reveal());

    $envelope = [
      'event' => 'incident_skipped',
      'payload' => [
        'correlation_id' => 'cid-2',
        'service' => 'kassa',
        'severity' => 'critical',
        'reason' => 'debounced',
      ],
    ];
    $this->assertTrue($this->ingester()->save($envelope));
  }

  public function testFallsBackToCurrentTimeWhenTimestampMissing(): void {
    $this->storage->loadByProperties(Argument::any())->willReturn([]);

    $before = time();

    $entity = $this->prophesize(EntityInterface::class);
    $entity->save()->shouldBeCalled();

    $this->storage->create(Argument::that(function ($values) use ($before) {
      return $values['received_at'] >= $before;
    }))->willReturn($entity->reveal());

    $envelope = [
      'event' => 'incident_diagnosed',
      'payload' => ['service' => 'kassa', 'severity' => 'critical'],
    ];
    $this->ingester()->save($envelope);
  }

}
