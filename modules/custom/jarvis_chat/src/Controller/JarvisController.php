<?php

namespace Drupal\jarvis_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class JarvisController extends ControllerBase {

  private const DEFAULT_BACKEND_URL = 'http://mcp-master:8080';
  private const REQUEST_TIMEOUT_SECONDS = 240;
  private const ELEVATED_ROLES = ['administrator', 'eventbeheerder'];
  private const JWT_TTL_SECONDS = 300;

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $logChannelFactory,
    protected AccountProxyInterface $userAccount,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('current_user'),
    );
  }

  public function page(): array {
    return [
      '#theme' => 'jarvis_chat',
      '#attached' => [
        'library' => ['jarvis_chat/widget'],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  public function chat(Request $request): JsonResponse {
    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body)) {
      return new JsonResponse(['error' => 'invalid JSON body'], 400);
    }

    // Accept both legacy {prompt: string} and multi-turn
    // {messages: [{role, content}, ...]} shapes — mcp-master does the
    // strict per-shape validation. We forward as-is to avoid duplicating
    // contracts on two sides; this proxy stays a thin pass-through.
    $hasPrompt = isset($body['prompt']) && trim((string) $body['prompt']) !== '';
    $hasMessages = isset($body['messages'])
      && is_array($body['messages'])
      && !empty($body['messages']);
    if (!$hasPrompt && !$hasMessages) {
      return new JsonResponse(['error' => 'prompt or messages required'], 400);
    }

    if (!$this->isElevated()) {
      return new JsonResponse(['error' => 'jarvis access requires admin or eventbeheerder role'], 403);
    }

    $url = getenv('MCP_MASTER_URL') ?: self::DEFAULT_BACKEND_URL;
    $options = [
      'json'    => $body,
      'timeout' => self::REQUEST_TIMEOUT_SECONDS,
    ];
    $bearer = $this->bearerToken();
    if ($bearer !== NULL) {
      $options['headers'] = ['Authorization' => 'Bearer ' . $bearer];
    }
    try {
      $response = $this->httpClient->request('POST', $url . '/chat', $options);
      $data = json_decode((string) $response->getBody(), TRUE);
      // Whitelist (not pass-through) so future mcp-master fields don't reach
      // the browser without an explicit decision — defense-in-depth against
      // a server-side bug that would otherwise leak PII (tokens, prompts, …)
      // straight into the DOM.
      return new JsonResponse([
        'answer' => $data['answer'] ?? '',
        'tool_trace' => $data['tool_trace'] ?? [],
      ]);
    }
    catch (GuzzleException $e) {
      $this->logChannelFactory->get('jarvis_chat')
        ->error('mcp-master proxy failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse(['error' => 'upstream error'], 502);
    }
  }

  private function isElevated(): bool {
    return !empty(array_intersect($this->userAccount->getRoles(), self::ELEVATED_ROLES));
  }

  private function bearerToken(): ?string {
    $secret = getenv('CHAT_JWT_SECRET');
    if ($secret !== FALSE && $secret !== '') {
      return self::mintJwt($secret, $this->userAccount);
    }
    $static = getenv('MCP_MASTER_BEARER_TOKEN');
    if ($static !== FALSE && $static !== '') {
      return $static;
    }
    return NULL;
  }

  /**
   * Mints HS256 JWT for mcp-master with role-derived scope.
   *
   * Static for direct unit-testability without container bootstrap.
   */
  public static function mintJwt(string $secret, AccountProxyInterface $user): string {
    $roles = $user->getRoles();
    $elevated = !empty(array_intersect($roles, self::ELEVATED_ROLES));
    return JWT::encode([
      'sub'   => (string) $user->id(),
      'scope' => $elevated ? 'read+act' : 'read',
      'exp'   => time() + self::JWT_TTL_SECONDS,
    ], $secret, 'HS256');
  }

}
