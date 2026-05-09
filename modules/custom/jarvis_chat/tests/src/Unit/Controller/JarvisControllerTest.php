<?php

namespace Drupal\Tests\jarvis_chat\Unit\Controller;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\jarvis_chat\Controller\JarvisController;
use Drupal\Tests\UnitTestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
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

  private const TEST_SECRET = 'test-secret-must-be-at-least-32-bytes-long-for-hs256';

  private function makeController(ClientInterface $http, ?AccountProxyInterface $user = NULL): JarvisController {
    $loggerChannel = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($loggerChannel);
    $user = $user ?? $this->createMock(AccountProxyInterface::class);
    return new JarvisController($http, $loggerFactory, $user);
  }

  private function makeUser(int $id, array $roles): AccountProxyInterface {
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('id')->willReturn($id);
    $user->method('getRoles')->willReturn($roles);
    return $user;
  }

  private function postRequest(array $body): Request {
    return Request::create('/api/jarvis/chat', 'POST', [], [], [], [], json_encode($body));
  }

  private function captureRequest(ClientInterface $http, &$captured): void {
    $http->method('request')->willReturnCallback(
      function ($method, $url, $options) use (&$captured) {
        $captured = $options;
        return new Response(200, [], json_encode(['answer' => 'ok']));
      }
    );
  }

  public function testRejectsMissingPromptAndMessages(): void {
    $controller = $this->makeController($this->createMock(ClientInterface::class));
    $response = $controller->chat($this->postRequest(['prompt' => '   ']));
    $this->assertSame(400, $response->getStatusCode());
    $this->assertStringContainsString('required', $response->getContent());
  }

  public function testForwardsMultiTurnMessagesToBackend(): void {
    $http = $this->createMock(ClientInterface::class);
    $captured = NULL;
    $http->method('request')->willReturnCallback(
      function ($method, $url, $options) use (&$captured) {
        $captured = $options['json'] ?? NULL;
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
        ['tool' => 'ping', 'server' => 'stub', 'ms' => 42, 'ok' => TRUE],
      ],
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
    $this->assertArrayNotHasKey('tokens', $payload);
    $this->assertArrayNotHasKey('iterations', $payload);
    $this->assertArrayNotHasKey('correlation_id', $payload);
  }

  public function testStaticBearerForwardedWhenJwtSecretUnset(): void {
    putenv('CHAT_JWT_SECRET');
    putenv('MCP_MASTER_BEARER_TOKEN=test-token-xyz');
    try {
      $http = $this->createMock(ClientInterface::class);
      $captured = NULL;
      $this->captureRequest($http, $captured);
      $controller = $this->makeController($http);
      $controller->chat($this->postRequest(['prompt' => 'hi']));
      $this->assertSame('Bearer test-token-xyz', $captured['headers']['Authorization'] ?? NULL);
    }
    finally {
      putenv('MCP_MASTER_BEARER_TOKEN');
    }
  }

  public function testNoAuthorizationHeaderWhenBothEnvsUnset(): void {
    putenv('CHAT_JWT_SECRET');
    putenv('MCP_MASTER_BEARER_TOKEN');
    $http = $this->createMock(ClientInterface::class);
    $captured = NULL;
    $this->captureRequest($http, $captured);
    $controller = $this->makeController($http);
    $controller->chat($this->postRequest(['prompt' => 'hi']));
    $this->assertArrayNotHasKey('headers', $captured);
  }

  public function testJwtMintedWithReadActScopeForAdmin(): void {
    putenv('CHAT_JWT_SECRET=' . self::TEST_SECRET);
    try {
      $http = $this->createMock(ClientInterface::class);
      $captured = NULL;
      $this->captureRequest($http, $captured);
      $admin = $this->makeUser(42, ['authenticated', 'administrator']);
      $controller = $this->makeController($http, $admin);
      $controller->chat($this->postRequest(['prompt' => 'hi']));

      $authHeader = $captured['headers']['Authorization'] ?? '';
      $this->assertStringStartsWith('Bearer ', $authHeader);
      $jwt = substr($authHeader, 7);
      $decoded = JWT::decode($jwt, new Key(self::TEST_SECRET, 'HS256'));
      $this->assertSame('42', $decoded->sub);
      $this->assertSame('read+act', $decoded->scope);
    }
    finally {
      putenv('CHAT_JWT_SECRET');
    }
  }

  public function testJwtMintedWithReadActScopeForEventbeheerder(): void {
    putenv('CHAT_JWT_SECRET=' . self::TEST_SECRET);
    try {
      $http = $this->createMock(ClientInterface::class);
      $captured = NULL;
      $this->captureRequest($http, $captured);
      $beheerder = $this->makeUser(7, ['authenticated', 'eventbeheerder']);
      $controller = $this->makeController($http, $beheerder);
      $controller->chat($this->postRequest(['prompt' => 'hi']));

      $jwt = substr($captured['headers']['Authorization'], 7);
      $decoded = JWT::decode($jwt, new Key(self::TEST_SECRET, 'HS256'));
      $this->assertSame('read+act', $decoded->scope);
    }
    finally {
      putenv('CHAT_JWT_SECRET');
    }
  }

  public function testJwtMintedWithReadScopeForVisitor(): void {
    putenv('CHAT_JWT_SECRET=' . self::TEST_SECRET);
    try {
      $http = $this->createMock(ClientInterface::class);
      $captured = NULL;
      $this->captureRequest($http, $captured);
      $visitor = $this->makeUser(99, ['authenticated', 'visitor']);
      $controller = $this->makeController($http, $visitor);
      $controller->chat($this->postRequest(['prompt' => 'hi']));

      $jwt = substr($captured['headers']['Authorization'], 7);
      $decoded = JWT::decode($jwt, new Key(self::TEST_SECRET, 'HS256'));
      $this->assertSame('99', $decoded->sub);
      $this->assertSame('read', $decoded->scope);
    }
    finally {
      putenv('CHAT_JWT_SECRET');
    }
  }

  public function testJwtPreferredOverStaticBearerWhenBothSet(): void {
    putenv('CHAT_JWT_SECRET=' . self::TEST_SECRET);
    putenv('MCP_MASTER_BEARER_TOKEN=fallback-static-token');
    try {
      $http = $this->createMock(ClientInterface::class);
      $captured = NULL;
      $this->captureRequest($http, $captured);
      $admin = $this->makeUser(1, ['administrator']);
      $controller = $this->makeController($http, $admin);
      $controller->chat($this->postRequest(['prompt' => 'hi']));

      $authHeader = $captured['headers']['Authorization'] ?? '';
      $this->assertStringNotContainsString('fallback-static-token', $authHeader);
      $jwt = substr($authHeader, 7);
      $this->assertSame(2, substr_count($jwt, '.'));
    }
    finally {
      putenv('CHAT_JWT_SECRET');
      putenv('MCP_MASTER_BEARER_TOKEN');
    }
  }

}
