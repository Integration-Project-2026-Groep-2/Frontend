<?php

namespace Drupal\Tests\jarvis_chat\Unit\Controller;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\jarvis_chat\Controller\JarvisController;
use Drupal\jarvis_chat\Service\JarvisJwtSigner;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\jarvis_chat\Controller\JarvisController
 * @group jarvis_chat
 */
class JarvisControllerTest extends UnitTestCase {

  private function makeController(ClientInterface $http, ?string $mintedJwt = null): JarvisController {
    $loggerChannel = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($loggerChannel);
    $signer = $this->createMock(JarvisJwtSigner::class);
    $signer->method('mint')->willReturn($mintedJwt);
    return new JarvisController($http, $loggerFactory, $signer);
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

  public function testSuccessfulProxyForwardsToolTrace(): void {
    $http = $this->createMock(ClientInterface::class);
    $upstream = [
      'answer' => 'pong',
      'tool_trace' => [
        ['tool' => 'ping', 'server' => 'stub', 'ms' => 42, 'ok' => true],
      ],
      // mcp-master also returns tokens/iterations/correlation_id; the
      // controller whitelist must drop those (Lars's v1.5 scope: only
      // tool_trace is rendered).
      'tokens' => ['input' => 100, 'output' => 50],
      'iterations' => 2,
      'correlation_id' => 'abc-123',
    ];
    $http->method('request')->willReturn(
      new Response(200, [], json_encode($upstream))
    );
    $controller = $this->makeController($http);
    $response = $controller->chat($this->postRequest(['prompt' => 'hi']));
    $this->assertSame(200, $response->getStatusCode());
    $payload = json_decode($response->getContent(), TRUE);
    $this->assertSame('pong', $payload['answer']);
    $this->assertCount(1, $payload['tool_trace']);
    $this->assertSame('ping', $payload['tool_trace'][0]['tool']);
    $this->assertSame('stub', $payload['tool_trace'][0]['server']);
    $this->assertSame(42, $payload['tool_trace'][0]['ms']);
    $this->assertTrue($payload['tool_trace'][0]['ok']);
    // Whitelist dropped these fields — confirm they never reach the browser.
    $this->assertArrayNotHasKey('tokens', $payload);
    $this->assertArrayNotHasKey('iterations', $payload);
    $this->assertArrayNotHasKey('correlation_id', $payload);
  }

  public function testApproveForwardsActionIdAndAuthorizationHeader(): void {
    $http = $this->createMock(ClientInterface::class);
    $captured = [];
    $http->method('request')->willReturnCallback(
      function ($method, $url, $options) use (&$captured) {
        $captured = ['method' => $method, 'url' => $url, 'options' => $options];
        return new Response(200, [], json_encode(['answer' => 'company created']));
      }
    );
    $controller = $this->makeController($http, 'fake-jwt-token');
    $response = $controller->approve($this->postRequest(['action_id' => 'uuid-123']));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('POST', $captured['method']);
    $this->assertStringEndsWith('/chat/approve', $captured['url']);
    $this->assertSame(['action_id' => 'uuid-123'], $captured['options']['json']);
    $this->assertSame('Bearer fake-jwt-token', $captured['options']['headers']['Authorization']);
  }

  public function testApproveMissingActionIdReturns400(): void {
    $controller = $this->makeController($this->createMock(ClientInterface::class));
    $response = $controller->approve($this->postRequest([]));
    $this->assertSame(400, $response->getStatusCode());
    $this->assertStringContainsString('action_id', $response->getContent());
  }

  public function testApproveUpstream409Surfaces(): void {
    $http = $this->createMock(ClientInterface::class);
    $http->method('request')->willThrowException(
      new RequestException(
        'conflict',
        new GuzzleRequest('POST', '/chat/approve'),
        new Response(409, [], json_encode(['error' => 'action already decided']))
      )
    );
    $controller = $this->makeController($http, 'fake-jwt-token');
    $response = $controller->approve($this->postRequest(['action_id' => 'uuid-409']));
    $this->assertSame(409, $response->getStatusCode());
    $payload = json_decode($response->getContent(), TRUE);
    $this->assertSame('action already decided', $payload['error']);
  }

  public function testRejectWithReasonForwardsBoth(): void {
    $http = $this->createMock(ClientInterface::class);
    $captured = null;
    $http->method('request')->willReturnCallback(
      function ($method, $url, $options) use (&$captured) {
        $captured = $options['json'] ?? null;
        return new Response(200, [], json_encode(['answer' => 'Action rejected: vendor mismatch']));
      }
    );
    $controller = $this->makeController($http, 'fake-jwt-token');
    $response = $controller->reject($this->postRequest([
      'action_id' => 'uuid-rej',
      'reason' => 'vendor mismatch',
    ]));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['action_id' => 'uuid-rej', 'reason' => 'vendor mismatch'], $captured);
  }

  public function testApproveResponseWhitelistDropsExtraFields(): void {
    $http = $this->createMock(ClientInterface::class);
    $upstream = [
      'answer' => 'company created',
      'tool_trace' => [
        ['tool' => 'create_company', 'server' => 'crm', 'ms' => 412, 'ok' => true, 'status' => 'executed', 'action_id' => 'uuid-x'],
      ],
      'tokens' => ['input' => 0, 'output' => 0],
      'iterations' => 0,
      'correlation_id' => 'cid-123',
    ];
    $http->method('request')->willReturn(
      new Response(200, [], json_encode($upstream))
    );
    $controller = $this->makeController($http, 'fake-jwt-token');
    $response = $controller->approve($this->postRequest(['action_id' => 'uuid-x']));
    $this->assertSame(200, $response->getStatusCode());
    $payload = json_decode($response->getContent(), TRUE);
    $this->assertSame('company created', $payload['answer']);
    $this->assertCount(1, $payload['tool_trace']);
    // Same defense-in-depth as /chat — extra fields don't reach the browser.
    $this->assertArrayNotHasKey('tokens', $payload);
    $this->assertArrayNotHasKey('iterations', $payload);
    $this->assertArrayNotHasKey('correlation_id', $payload);
  }

}
