<?php

namespace Drupal\jarvis_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jarvis_chat\Service\JarvisJwtSigner;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JarvisController extends ControllerBase {

  private const DEFAULT_BACKEND_URL = 'http://mcp-master:8080';
  private const REQUEST_TIMEOUT_SECONDS = 240;

  /**
   * Wall-clock cap for /chat/stream. Mcp-master enforces 600s; we hold
   * the upstream connection +10s so the backend's terminal error event
   * with correlation_id reaches the client before our own timeout fires.
   */
  private const STREAM_TIMEOUT_SECONDS = 610;

  private const ELEVATED_ROLES = ['administrator', 'event_manager'];

  /**
   * mcp-master's documented /chat/approve + /chat/reject 4xx errors
   * (HTTP_API.md §1.5). Anything outside this set collapses to a generic
   * 'upstream error' so a regression that emits Salesforce stack traces
   * or auth-debug fragments doesn't leak straight to the browser DOM.
   */
  private const KNOWN_UPSTREAM_ERRORS = [
    'action not found',
    'action expired',
    'action already decided',
    'user mismatch',
    'scope read+act required',
    'action_id required',
  ];

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $logChannelFactory,
    protected JarvisJwtSigner $jwtSigner,
    protected AccountInterface $account,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('jarvis_chat.jwt_signer'),
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
    if (($denial = $this->assertElevatedRole()) !== NULL) {
      return $denial;
    }

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

    return $this->forwardToMaster('/chat', $body, FALSE);
  }

  public function approve(Request $request): JsonResponse {
    if (($denial = $this->assertElevatedRole()) !== NULL) {
      return $denial;
    }
    $body = json_decode($request->getContent(), TRUE);
    if (!$this->isValidActionIdBody($body)) {
      return new JsonResponse(['error' => 'action_id required'], 400);
    }
    return $this->forwardToMaster('/chat/approve', ['action_id' => $body['action_id']], TRUE);
  }

  public function reject(Request $request): JsonResponse {
    if (($denial = $this->assertElevatedRole()) !== NULL) {
      return $denial;
    }
    $body = json_decode($request->getContent(), TRUE);
    if (!$this->isValidActionIdBody($body)) {
      return new JsonResponse(['error' => 'action_id required'], 400);
    }
    $payload = ['action_id' => $body['action_id']];
    if (isset($body['reason']) && is_string($body['reason']) && trim($body['reason']) !== '') {
      $payload['reason'] = trim($body['reason']);
    }
    return $this->forwardToMaster('/chat/reject', $payload, TRUE);
  }

  /**
   * Streaming counterpart of `chat()`. Returns text/event-stream that
   * pipes mcp-master's /chat/stream ProgressEvent feed verbatim to the
   * browser. Body validation + role-gate identical to `chat()`.
   *
   * Guzzle's `stream: true` keeps the upstream connection open and
   * returns a PSR-7 response whose body is a streamable resource. The
   * StreamedResponse callback drains it chunk-per-chunk with
   * `flush()` after each write — without that, Drupal + PHP-FPM
   * would buffer the entire response body and defeat the streaming
   * UX. `X-Accel-Buffering: no` signals nginx/Cloudflare to bypass
   * their own buffering on this response.
   *
   * Pre-stream failures (Guzzle exception before bytes flow) return
   * JSON 4xx/502 — same shape as `chat()` so the JS error-handler
   * stays single-codepath.
   */
  public function streamChat(Request $request): Response {
    if (($denial = $this->assertElevatedRole()) !== NULL) {
      return $denial;
    }

    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body)) {
      return new JsonResponse(['error' => 'invalid JSON body'], 400);
    }
    $hasPrompt = isset($body['prompt']) && trim((string) $body['prompt']) !== '';
    $hasMessages = isset($body['messages'])
      && is_array($body['messages'])
      && !empty($body['messages']);
    if (!$hasPrompt && !$hasMessages) {
      return new JsonResponse(['error' => 'prompt or messages required'], 400);
    }

    $headers = ['Accept' => 'text/event-stream'];
    if (($auth = $this->buildAuthorizationHeader()) !== NULL) {
      $headers['Authorization'] = $auth;
    }

    try {
      $upstream = $this->httpClient->request(
        'POST',
        $this->backendBaseUrl() . '/chat/stream',
        [
          'json' => $body,
          'headers' => $headers,
          'stream' => TRUE,
          'timeout' => self::STREAM_TIMEOUT_SECONDS,
          'read_timeout' => self::STREAM_TIMEOUT_SECONDS,
        ],
      );
    }
    catch (RequestException $e) {
      $upstreamResp = $e->getResponse();
      if ($upstreamResp !== NULL) {
        $status = $upstreamResp->getStatusCode();
        if ($status >= 400 && $status < 500) {
          $errBody = json_decode((string) $upstreamResp->getBody(), TRUE);
          return new JsonResponse(['error' => self::safeUpstreamError($errBody)], $status);
        }
      }
      $this->logChannelFactory->get('jarvis_chat')
        ->error('mcp-master /chat/stream failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse(['error' => 'upstream error'], 502);
    }
    catch (GuzzleException $e) {
      $this->logChannelFactory->get('jarvis_chat')
        ->error('mcp-master /chat/stream failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse(['error' => 'upstream error'], 502);
    }

    $response = new StreamedResponse(function () use ($upstream): void {
      // Force every echo to flush immediately. Drupal + PHP-FPM otherwise
      // buffer the response until the script ends, defeating SSE.
      @ob_implicit_flush(TRUE);
      while (ob_get_level() > 0) {
        @ob_end_flush();
      }
      $bodyStream = $upstream->getBody();
      while (!$bodyStream->eof()) {
        $chunk = $bodyStream->read(8192);
        if ($chunk !== '') {
          echo $chunk;
          flush();
        }
      }
    });

    $response->headers->set('Content-Type', 'text/event-stream');
    $response->headers->set('Cache-Control', 'no-cache, no-transform');
    // Tells nginx/Cloudflare not to buffer this response — `text/event-stream`
    // already hints it but the explicit header is honoured by more proxies.
    $response->headers->set('X-Accel-Buffering', 'no');
    return $response;
  }

  /**
   * Belt-and-braces gate: even if `'use jarvis chat'` permission ever
   * leaks to a non-elevated role by config-typo, controller still 403's.
   * Defense-in-depth complementing the route-level _permission check.
   */
  private function assertElevatedRole(): ?JsonResponse {
    $roles = $this->account->getRoles(TRUE);
    if (empty(array_intersect($roles, self::ELEVATED_ROLES))) {
      return new JsonResponse(['error' => 'jarvis chat requires elevated role'], 403);
    }
    return NULL;
  }

  /**
   * is_string check stops `(string) $array_action_id === "Array"` PHP
   * warnings + a downstream UUID-parse 400 from mcp-master that the user
   * can't action on. Also catches null and other scalar shapes.
   */
  private function isValidActionIdBody(mixed $body): bool {
    return is_array($body)
      && isset($body['action_id'])
      && is_string($body['action_id'])
      && trim($body['action_id']) !== '';
  }

  /**
   * POST $body to mcp-master at $path, return whitelisted JsonResponse.
   *
   * $surfaceUpstreamStatus controls error semantics:
   * - FALSE (legacy /chat): any GuzzleException → 502 "upstream error"
   * - TRUE (/chat/approve, /chat/reject): forward upstream 4xx as-is so JS
   *   can render the specific 403/404/409/410 message inline; 5xx still
   *   collapses to 502 to keep cause-chains out of the browser.
   */
  private function forwardToMaster(string $path, array $body, bool $surfaceUpstreamStatus): JsonResponse {
    $url = $this->backendBaseUrl();
    $options = [
      'json' => $body,
      'timeout' => self::REQUEST_TIMEOUT_SECONDS,
    ];
    $authHeader = $this->buildAuthorizationHeader();
    if ($authHeader !== NULL) {
      $options['headers'] = ['Authorization' => $authHeader];
    }

    try {
      $response = $this->httpClient->request('POST', $url . $path, $options);
      $data = json_decode((string) $response->getBody(), TRUE);
      return new JsonResponse([
        'answer' => $data['answer'] ?? '',
        'tool_trace' => self::filterToolTrace($data['tool_trace'] ?? []),
      ]);
    }
    catch (RequestException $e) {
      // Forward upstream 4xx with body's `error` field so JS can show
      // "action not found" / "action already decided" / "action expired".
      $upstream = $e->getResponse();
      if ($surfaceUpstreamStatus && $upstream !== NULL) {
        $status = $upstream->getStatusCode();
        if ($status >= 400 && $status < 500) {
          $errBody = json_decode((string) $upstream->getBody(), TRUE);
          return new JsonResponse(['error' => self::safeUpstreamError($errBody)], $status);
        }
      }
      $this->logChannelFactory->get('jarvis_chat')
        ->error('mcp-master @path failed: @msg', ['@path' => $path, '@msg' => $e->getMessage()]);
      return new JsonResponse(['error' => 'upstream error'], 502);
    }
    catch (GuzzleException $e) {
      $this->logChannelFactory->get('jarvis_chat')
        ->error('mcp-master @path failed: @msg', ['@path' => $path, '@msg' => $e->getMessage()]);
      return new JsonResponse(['error' => 'upstream error'], 502);
    }
  }

  /**
   * Per-entry whitelist for tool_trace items reaching the browser.
   *
   * Defense-in-depth — the documented contract (HTTP_API.md §1.5) only
   * exposes these 7 fields. If `CHAT_TRACE_INCLUDE_ARGS=true` ever flips
   * on mcp-master, `args` would otherwise pass through with PII (BTW,
   * emails, names from CRM-MCP write-tools). Drupal becomes the second
   * wall against accidental DOM-side leaks.
   */
  private const TOOL_TRACE_FIELDS = ['tool', 'server', 'ms', 'ok', 'status', 'action_id', 'error'];

  private static function filterToolTrace(mixed $trace): array {
    if (!is_array($trace)) {
      return [];
    }
    $allow = array_flip(self::TOOL_TRACE_FIELDS);
    return array_values(array_map(
      fn($t) => is_array($t) ? array_intersect_key($t, $allow) : [],
      $trace,
    ));
  }

  /**
   * Filters upstream error strings against the documented allowlist.
   *
   * mcp-master regressions could otherwise leak Salesforce stack-traces or
   * JWT-debug fragments into the browser DOM via /chat/approve|reject 4xx.
   */
  private static function safeUpstreamError(mixed $errBody): string {
    if (!is_array($errBody) || !isset($errBody['error']) || !is_string($errBody['error'])) {
      return 'upstream error';
    }
    $msg = trim($errBody['error']);
    if (in_array(strtolower($msg), self::KNOWN_UPSTREAM_ERRORS, TRUE)) {
      return $msg;
    }
    return 'upstream error';
  }

  /**
   * Validates MCP_MASTER_URL — falls back to default on any malformed value.
   *
   * Threat: env-var compromise (rotated `.env` leaks have happened in this
   * sprint) could redirect a freshly-minted JWT to attacker.tld. Validating
   * scheme/host/path/query/fragment closes the SSRF seam without locking us
   * into a host-allowlist (which differs between local-dev and prod).
   */
  private function backendBaseUrl(): string {
    $raw = getenv('MCP_MASTER_URL');
    if ($raw === FALSE || $raw === '') {
      return self::DEFAULT_BACKEND_URL;
    }
    $parsed = parse_url($raw);
    $isValid = is_array($parsed)
      && in_array($parsed['scheme'] ?? '', ['http', 'https'], TRUE)
      && !empty($parsed['host'])
      && empty($parsed['query'])
      && empty($parsed['fragment'])
      && (!isset($parsed['path']) || $parsed['path'] === '' || $parsed['path'] === '/');
    if (!$isValid) {
      $this->logChannelFactory->get('jarvis_chat')
        ->warning('Invalid MCP_MASTER_URL — falling back to default');
      return self::DEFAULT_BACKEND_URL;
    }
    return rtrim($raw, '/');
  }

  /**
   * Authorization header value, or NULL when no auth configured.
   *
   * JWT first (R2 scope-aware path) so mcp-master can extract claims and
   * enforce proposer-equals-confirmer. Static MCP_MASTER_BEARER_TOKEN
   * stays as fallback for transitional deploys where Drupal hasn't yet
   * received CHAT_JWT_SECRET — keeps PR #43's read-only path alive.
   */
  private function buildAuthorizationHeader(): ?string {
    $jwt = $this->jwtSigner->mint();
    if ($jwt !== NULL) {
      return 'Bearer ' . $jwt;
    }
    $bearer = getenv('MCP_MASTER_BEARER_TOKEN');
    if ($bearer !== FALSE && $bearer !== '') {
      return 'Bearer ' . $bearer;
    }
    return NULL;
  }

}
