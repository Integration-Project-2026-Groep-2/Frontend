<?php

namespace Drupal\Tests\jarvis_chat\Unit\Controller;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\jarvis_chat\Controller\JarvisController;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\jarvis_chat\Controller\JarvisController
 * @group jarvis_chat
 */
class JarvisControllerTest extends UnitTestCase {

  private function makeController(ClientInterface $http): JarvisController {
    $loggerChannel = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($loggerChannel);
    return new JarvisController($http, $loggerFactory);
  }

  private function postRequest(array $body): Request {
    return Request::create('/api/jarvis/chat', 'POST', [], [], [], [], json_encode($body));
  }

  public function testRejectsMissingPromptAndMessages(): void {
    $controller = $this->makeController($this->createMock(ClientInterface::class));
    $response = $controller->chat($this->postRequest(['prompt' => '   ']));
    $this->assertSame(400, $response->getStatusCode());
    $this->assertStringContainsString('required', $response->getContent());
  }

  public function testForwardsMultiTurnMessagesToBackend(): void {
    $http = $this->createMock(ClientInterface::class);
    // Capture the body sent to mcp-master so we can assert it was
    // forwarded as-is (not stripped down to {prompt: ...}).
    $captured = null;
    $http->method('request')->willReturnCallback(
      function ($method, $url, $options) use (&$captured) {
        $captured = $options['json'] ?? null;
        return new Response(200, [], json_encode(['answer' => 'multi-turn ok']));
      }
    );
    $controller = $this->makeController($http);
    $messages = [
      ['role' => 'user', 'content' => 'q1'],
      ['role' => 'assistant', 'content' => 'a1'],
      ['role' => 'user', 'content' => 'q2'],
    ];
    $response = $controller->chat($this->postRequest(['messages' => $messages]));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['messages' => $messages], $captured);
  }

  public function testUpstreamFailureReturns502(): void {
    $http = $this->createMock(ClientInterface::class);
    $http->method('request')->willThrowException(
      new ConnectException('refused', new GuzzleRequest('POST', '/chat'))
    );
    $controller = $this->makeController($http);
    $response = $controller->chat($this->postRequest(['prompt' => 'hi']));
    $this->assertSame(502, $response->getStatusCode());
  }

  public function testSuccessfulProxyReturnsAnswer(): void {
    $http = $this->createMock(ClientInterface::class);
    $http->method('request')->willReturn(
      new Response(200, [], json_encode(['answer' => 'hello world']))
    );
    $controller = $this->makeController($http);
    $response = $controller->chat($this->postRequest(['prompt' => 'hi']));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('hello world', $response->getContent());
  }

}
