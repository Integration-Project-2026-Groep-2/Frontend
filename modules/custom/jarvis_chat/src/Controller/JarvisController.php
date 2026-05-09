<?php

namespace Drupal\jarvis_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class JarvisController extends ControllerBase {

  private const DEFAULT_BACKEND_URL = 'http://mcp-master:8080';
  private const REQUEST_TIMEOUT_SECONDS = 240;

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $logChannelFactory,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('http_client'),
      $container->get('logger.factory'),
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

    $url = getenv('MCP_MASTER_URL') ?: self::DEFAULT_BACKEND_URL;
    $options = [
      'json'    => $body,
      'timeout' => self::REQUEST_TIMEOUT_SECONDS,
    ];
    $bearer = getenv('MCP_MASTER_BEARER_TOKEN');
    if ($bearer !== FALSE && $bearer !== '') {
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

}
