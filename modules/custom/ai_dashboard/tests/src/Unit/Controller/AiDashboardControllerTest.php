<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Unit\Controller;

use Drupal\ai_dashboard\Controller\AiDashboardController;
use Drupal\ai_dashboard\Service\IncidentRepository;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\ai_dashboard\Controller\AiDashboardController
 * @group ai_dashboard
 */
class AiDashboardControllerTest extends UnitTestCase {

  use ProphecyTrait;

  private function makeController(array $roles, IncidentRepository $repository): AiDashboardController {
    $account = $this->prophesize(AccountInterface::class);
    $account->getRoles(TRUE)->willReturn($roles);
    return new AiDashboardController($account->reveal(), $repository);
  }

  private function fakeEntity(array $fields, int $id): object {
    return new class($fields, $id) {

      public function __construct(private array $fields, private int $id) {
      }

      public function id(): int {
        return $this->id;
      }

      public function get(string $name): object {
        $value = $this->fields[$name] ?? NULL;
        return new class($value) {

          public function __construct(public mixed $value) {
          }

        };
      }

    };
  }

  public function testListRejectsNonElevatedRole(): void {
    $repository = $this->prophesize(IncidentRepository::class);
    $repository->recent(Argument::any(), Argument::any())->shouldNotBeCalled();
    $controller = $this->makeController(['visitor'], $repository->reveal());

    $response = $controller->list(Request::create('/api/ai/incidents'));

    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('elevated role', $response->getContent());
  }

  public function testListReturnsRecentIncidentsForAdministrator(): void {
    $entity = $this->fakeEntity([
      'event_type' => 'incident_diagnosed',
      'correlation_id' => 'cid-1',
      'service' => 'kassa',
      'severity' => 'critical',
      'confidence' => 'high',
      'received_at' => 1747000000,
      'payload_json' => json_encode([
        'payload' => ['diagnosis' => ['root_cause' => 'pool exhausted']],
      ]),
    ], 42);

    $repository = $this->prophesize(IncidentRepository::class);
    $repository->recent(50, 0)->willReturn([$entity]);
    $controller = $this->makeController(['administrator'], $repository->reveal());

    $response = $controller->list(Request::create('/api/ai/incidents'));

    $this->assertSame(200, $response->getStatusCode());
    $payload = json_decode($response->getContent(), TRUE);
    $this->assertCount(1, $payload['incidents']);
    $this->assertSame(42, $payload['incidents'][0]['id']);
    $this->assertSame('kassa', $payload['incidents'][0]['service']);
    $this->assertSame('pool exhausted', $payload['incidents'][0]['root_cause_preview']);
  }

  public function testListAcceptsEventManagerRole(): void {
    $repository = $this->prophesize(IncidentRepository::class);
    $repository->recent(Argument::any(), Argument::any())->willReturn([]);
    $controller = $this->makeController(['event_manager'], $repository->reveal());

    $response = $controller->list(Request::create('/api/ai/incidents'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['incidents' => []], json_decode($response->getContent(), TRUE));
  }

  public function testListForwardsSinceAndLimitQueryParameters(): void {
    $repository = $this->prophesize(IncidentRepository::class);
    $repository->recent(25, 1747000000)->shouldBeCalled()->willReturn([]);
    $controller = $this->makeController(['administrator'], $repository->reveal());

    $controller->list(Request::create('/api/ai/incidents?since=1747000000&limit=25'));
  }

  public function testDetailRejectsNonElevatedRole(): void {
    $repository = $this->prophesize(IncidentRepository::class);
    $repository->load(Argument::any())->shouldNotBeCalled();
    $controller = $this->makeController(['authenticated'], $repository->reveal());

    $response = $controller->detail(7);

    $this->assertSame(403, $response->getStatusCode());
  }

  public function testDetailReturns404WhenEntityMissing(): void {
    $repository = $this->prophesize(IncidentRepository::class);
    $repository->load(99)->willReturn(NULL);
    $controller = $this->makeController(['administrator'], $repository->reveal());

    $response = $controller->detail(99);

    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame(['error' => 'not found'], json_decode($response->getContent(), TRUE));
  }

  public function testDetailReturnsFullEnvelopeWhenEntityFound(): void {
    $envelope = [
      'event' => 'incident_diagnosed',
      'payload' => [
        'diagnosis' => ['root_cause' => 'pool exhausted', 'confidence' => 'high'],
        'tool_trace' => [['server' => 'controlroom', 'tool' => 'fetch_logs', 'ms' => 234]],
      ],
    ];
    $entity = $this->fakeEntity([
      'event_type' => 'incident_diagnosed',
      'correlation_id' => 'cid-7',
      'service' => 'crm',
      'severity' => 'critical',
      'confidence' => 'high',
      'received_at' => 1747000100,
      'processed_at' => 1747000101,
      'payload_json' => json_encode($envelope),
    ], 7);

    $repository = $this->prophesize(IncidentRepository::class);
    $repository->load(7)->willReturn($entity);
    $controller = $this->makeController(['administrator'], $repository->reveal());

    $response = $controller->detail(7);

    $this->assertSame(200, $response->getStatusCode());
    $payload = json_decode($response->getContent(), TRUE);
    $this->assertSame(7, $payload['id']);
    $this->assertSame('crm', $payload['service']);
    $this->assertSame($envelope, $payload['envelope']);
  }

  public function testPageReturnsRenderArrayWithLibrary(): void {
    $repository = $this->prophesize(IncidentRepository::class);
    $controller = $this->makeController(['administrator'], $repository->reveal());

    $build = $controller->page();

    $this->assertSame('ai_dashboard', $build['#theme']);
    $this->assertSame(['ai_dashboard/dashboard'], $build['#attached']['library']);
    $this->assertSame(0, $build['#cache']['max-age']);
  }

}
