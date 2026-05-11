<?php

declare(strict_types=1);

namespace Drupal\ai_dashboard\Controller;

use Drupal\ai_dashboard\Service\IncidentRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AiDashboardController extends ControllerBase {

  private const ELEVATED_ROLES = ['administrator', 'event_manager'];

  public function __construct(
    protected AccountInterface $account,
    protected IncidentRepository $repository,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('current_user'),
      $container->get('ai_dashboard.incident_repository'),
    );
  }

  public function page(): array {
    return [
      '#theme' => 'ai_dashboard',
      '#attached' => [
        'library' => ['ai_dashboard/dashboard'],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  public function list(Request $request): JsonResponse {
    if (($denial = $this->assertElevatedRole()) !== NULL) {
      return $denial;
    }
    $since = (int) $request->query->get('since', '0');
    $limit = (int) $request->query->get('limit', '50');
    $entities = $this->repository->recent($limit, $since);
    $items = array_map([IncidentRepository::class, 'toListItem'], array_values($entities));
    return new JsonResponse(['incidents' => $items]);
  }

  public function detail(int $id): JsonResponse {
    if (($denial = $this->assertElevatedRole()) !== NULL) {
      return $denial;
    }
    $entity = $this->repository->load($id);
    if ($entity === NULL) {
      return new JsonResponse(['error' => 'not found'], 404);
    }
    return new JsonResponse(IncidentRepository::toDetail($entity));
  }

  /**
   * Belt-and-braces gate complementing the route-level _permission check.
   */
  private function assertElevatedRole(): ?JsonResponse {
    $roles = $this->account->getRoles(TRUE);
    if (empty(array_intersect($roles, self::ELEVATED_ROLES))) {
      return new JsonResponse(['error' => 'view ai dashboard requires elevated role'], 403);
    }
    return NULL;
  }

}
