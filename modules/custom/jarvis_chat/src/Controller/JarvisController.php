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
  private const REQUEST_TIMEOUT_SECONDS = 60;

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('http_client'),
      $container->get('logger.factory'),
    );
  }

  public function page(): array {
    return ['#markup' => 'Jarvis'];
  }

  public function chat(Request $request): JsonResponse {
    $body = json_decode($request->getContent(), TRUE);
    $prompt = trim($body['prompt'] ?? '');
    if ($prompt === '') {
      return new JsonResponse(['error' => 'prompt is empty'], 400);
    }

    $url = getenv('MCP_MASTER_URL') ?: self::DEFAULT_BACKEND_URL;
    try {
      $response = $this->httpClient->post($url . '/chat', [
        'json'    => ['prompt' => $prompt],
        'timeout' => self::REQUEST_TIMEOUT_SECONDS,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      return new JsonResponse(['answer' => $data['answer'] ?? '']);
    }
    catch (GuzzleException $e) {
      $this->loggerFactory->get('jarvis_chat')
        ->error('mcp-master proxy failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse(['error' => 'upstream error'], 502);
    }
  }

}
