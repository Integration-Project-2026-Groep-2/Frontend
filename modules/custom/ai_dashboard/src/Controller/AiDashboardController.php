<?php

declare(strict_types=1);

namespace Drupal\ai_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class AiDashboardController extends ControllerBase {

  private const ELEVATED_ROLES = ['administrator', 'event_manager'];

  public function __construct(
    protected AccountInterface $account,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('current_user'));
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

  public function list(): JsonResponse {
    if (($denial = $this->assertElevatedRole()) !== NULL) {
      return $denial;
    }
    return new JsonResponse(['incidents' => []]);
  }

  public function detail(int $id): JsonResponse {
    if (($denial = $this->assertElevatedRole()) !== NULL) {
      return $denial;
    }
    return new JsonResponse(['error' => 'not found'], 404);
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
