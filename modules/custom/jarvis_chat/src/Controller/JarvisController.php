<?php

namespace Drupal\jarvis_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\jarvis_chat\Service\JarvisJwtSigner;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class JarvisController extends ControllerBase {

  private const DEFAULT_BACKEND_URL = 'http://mcp-master:8080';
  private const REQUEST_TIMEOUT_SECONDS = 240;

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $logChannelFactory,
    protected JarvisJwtSigner $jwtSigner,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('jarvis_chat.jwt_signer'),
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

    return $this->forwardToMaster('/chat', $body, FALSE);
  }

  public function approve(Request $request): JsonResponse {
    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body) || !isset($body['action_id']) || trim((string) $body['action_id']) === '') {
      return new JsonResponse(['error' => 'action_id required'], 400);
    }
    return $this->forwardToMaster('/chat/approve', ['action_id' => $body['action_id']], TRUE);
  }

  public function reject(Request $request): JsonResponse {
    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body) || !isset($body['action_id']) || trim((string) $body['action_id']) === '') {
      return new JsonResponse(['error' => 'action_id required'], 400);
    }
    $payload = ['action_id' => $body['action_id']];
    if (isset($body['reason']) && is_string($body['reason']) && trim($body['reason']) !== '') {
      $payload['reason'] = trim($body['reason']);
    }
    return $this->forwardToMaster('/chat/reject', $payload, TRUE);
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
    $url = getenv('MCP_MASTER_URL') ?: self::DEFAULT_BACKEND_URL;
    $options = [
      'json' => $body,
      'timeout' => self::REQUEST_TIMEOUT_SECONDS,
    ];
    $jwt = $this->jwtSigner->mint();
    if ($jwt !== NULL) {
      $options['headers'] = ['Authorization' => 'Bearer ' . $jwt];
    }

    try {
      $response = $this->httpClient->request('POST', $url . $path, $options);
      $data = json_decode((string) $response->getBody(), TRUE);
      return new JsonResponse([
        'answer' => $data['answer'] ?? '',
        'tool_trace' => $data['tool_trace'] ?? [],
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
          $msg = (is_array($errBody) && isset($errBody['error'])) ? $errBody['error'] : 'upstream error';
          return new JsonResponse(['error' => $msg], $status);
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

}
