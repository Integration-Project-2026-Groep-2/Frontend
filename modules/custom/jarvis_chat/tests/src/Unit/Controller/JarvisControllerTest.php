<?php

namespace Drupal\Tests\jarvis_chat\Unit\Controller;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jarvis_chat\Controller\JarvisController;
use Drupal\jarvis_chat\Service\JarvisJwtSigner;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @coversDefaultClass \Drupal\jarvis_chat\Controller\JarvisController
 * @group jarvis_chat
 */
class JarvisControllerTest extends UnitTestCase {

  private function makeController(
    ClientInterface $http,
    ?string $mintedJwt = null,
    array $roles = ['administrator', 'authenticated'],
  ): JarvisController {
    $loggerChannel = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($loggerChannel);
    $signer = $this->createMock(JarvisJwtSigner::class);
    $signer->method('mint')->willReturn($mintedJwt);
    $account = $this->createMock(AccountInterface::class);
    // ControllerBase's getRoles($exclude_locked_roles=TRUE) drops 'authenticated'
    // — match that behavior in the mock so role-gate sees only the elevated set.
    $account->method('getRoles')->willReturnCallback(
      fn(bool $excludeLocked = FALSE) => $excludeLocked
        ? array_values(array_diff($roles, ['authenticated', 'anonymous']))
        : $roles
    );
    return new JarvisController($http, $loggerFactory, $signer, $account);
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

  public function testApproveRejectsNonStringActionId(): void {
    // Catches array/null/bool action_id before they reach mcp-master and
    // produce ugly PHP "Array to string conversion" warnings on the way to
    // a confusing 400.
    $controller = $this->makeController($this->createMock(ClientInterface::class));
    $response = $controller->approve($this->postRequest(['action_id' => ['x']]));
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

  public function testStaticBearerFallbackUsedWhenJwtAbsent(): void {
    // PR #43 retained: when JWT signer can't mint (no CHAT_JWT_SECRET) but
    // MCP_MASTER_BEARER_TOKEN is set, fall back to static bearer so legacy
    // read-only deploys keep working before R2 secret is wired.
    putenv('MCP_MASTER_BEARER_TOKEN=legacy-static');
    try {
      $http = $this->createMock(ClientInterface::class);
      $captured = NULL;
      $http->method('request')->willReturnCallback(
        function ($method, $url, $options) use (&$captured) {
          $captured = $options;
          return new Response(200, [], json_encode(['answer' => 'ok']));
        }
      );
      $controller = $this->makeController($http);
      $controller->chat($this->postRequest(['prompt' => 'hi']));
      $this->assertSame('Bearer legacy-static', $captured['headers']['Authorization'] ?? NULL);
    }
    finally {
      putenv('MCP_MASTER_BEARER_TOKEN');
    }
  }

  public function testNoAuthorizationHeaderWhenNeitherJwtNorBearer(): void {
    putenv('MCP_MASTER_BEARER_TOKEN');
    $http = $this->createMock(ClientInterface::class);
    $captured = NULL;
    $http->method('request')->willReturnCallback(
      function ($method, $url, $options) use (&$captured) {
        $captured = $options;
        return new Response(200, [], json_encode(['answer' => 'ok']));
      }
    );
    $controller = $this->makeController($http);
    $controller->chat($this->postRequest(['prompt' => 'hi']));
    $this->assertArrayNotHasKey('headers', $captured);
  }

  public function testChatRejectsAuthenticatedUserWithoutElevatedRole(): void {
    $controller = $this->makeController(
      $this->createMock(ClientInterface::class),
      null,
      ['authenticated'],
    );
    $response = $controller->chat($this->postRequest(['prompt' => 'hi']));
    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('elevated role', $response->getContent());
  }

  public function testChatAcceptsAdministrator(): void {
    $http = $this->createMock(ClientInterface::class);
    $http->method('request')->willReturn(
      new Response(200, [], json_encode(['answer' => 'ok']))
    );
    $controller = $this->makeController($http, null, ['administrator', 'authenticated']);
    $response = $controller->chat($this->postRequest(['prompt' => 'hi']));
    $this->assertSame(200, $response->getStatusCode());
  }

  public function testChatAcceptsEventBeheerder(): void {
    $http = $this->createMock(ClientInterface::class);
    $http->method('request')->willReturn(
      new Response(200, [], json_encode(['answer' => 'ok']))
    );
    $controller = $this->makeController($http, null, ['event_manager', 'authenticated']);
    $response = $controller->chat($this->postRequest(['prompt' => 'hi']));
    $this->assertSame(200, $response->getStatusCode());
  }

  public function testApproveRejectsNonElevatedRole(): void {
    $controller = $this->makeController(
      $this->createMock(ClientInterface::class),
      null,
      ['visitor', 'authenticated'],
    );
    $response = $controller->approve($this->postRequest(['action_id' => 'uuid-x']));
    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('elevated role', $response->getContent());
  }

  public function testRejectRejectsNonElevatedRole(): void {
    $controller = $this->makeController(
      $this->createMock(ClientInterface::class),
      null,
      ['visitor', 'authenticated'],
    );
    $response = $controller->reject($this->postRequest(['action_id' => 'uuid-x']));
    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('elevated role', $response->getContent());
  }

  public function testInvalidBackendUrlFallsBackToDefault(): void {
    putenv('MCP_MASTER_URL=http://attacker.tld/log?x=1');
    try {
      $http = $this->createMock(ClientInterface::class);
      $captured = NULL;
      $http->method('request')->willReturnCallback(
        function ($method, $url, $options) use (&$captured) {
          $captured = $url;
          return new Response(200, [], json_encode(['answer' => 'ok']));
        }
      );
      $controller = $this->makeController($http);
      $controller->chat($this->postRequest(['prompt' => 'hi']));
      $this->assertStringStartsWith('http://mcp-master:8080', (string) $captured);
      $this->assertStringEndsWith('/chat', (string) $captured);
    }
    finally {
      putenv('MCP_MASTER_URL');
    }
  }

  public function testApproveUnknownUpstreamErrorCollapsedToGeneric(): void {
    // Defense-in-depth: a regression in mcp-master that emits a verbose
    // error (Salesforce stack-trace, JWT-debug, secret fragment) must NOT
    // reach the browser. Only the documented HTTP_API.md §1.5 error-strings
    // pass through; everything else collapses to 'upstream error'.
    $http = $this->createMock(ClientInterface::class);
    $http->method('request')->willThrowException(
      new RequestException(
        'leak',
        new GuzzleRequest('POST', '/chat/approve'),
        new Response(409, [], json_encode(['error' => 'verbose internal stack trace 0x123 secret=abc']))
      )
    );
    $controller = $this->makeController($http, 'fake-jwt-token');
    $response = $controller->approve($this->postRequest(['action_id' => 'uuid-x']));
    $this->assertSame(409, $response->getStatusCode());
    $payload = json_decode($response->getContent(), TRUE);
    $this->assertSame('upstream error', $payload['error']);
  }

  public function testStreamChatForwardsBearerAndOpensStream(): void {
    $http = $this->createMock(ClientInterface::class);
    $captured = NULL;
    $http->method('request')->willReturnCallback(
      function ($method, $url, $options) use (&$captured) {
        $captured = $options;
        // mcp-master's /chat/stream returns text/event-stream. For the
        // unit-level pipe-test a static frame suffices — the StreamedResponse
        // callback pipes whatever Guzzle hands us, doesn't introspect it.
        return new Response(
          200,
          ['Content-Type' => 'text/event-stream'],
          "event: done\ndata: {\"event\":\"done\"}\n\n"
        );
      }
    );
    $controller = $this->makeController($http, 'jwt-token');
    $request = Request::create(
      '/api/jarvis/chat/stream', 'POST', [], [], [], [],
      json_encode(['messages' => [['role' => 'user', 'content' => 'q']]])
    );

    $response = $controller->streamChat($request);

    $this->assertInstanceOf(StreamedResponse::class, $response);
    $this->assertSame('text/event-stream', $response->headers->get('Content-Type'));
    // Symfony may append `, private` to the Cache-Control we set; only the
    // two directives that matter for SSE-proxy behaviour need to be present.
    $cacheControl = $response->headers->get('Cache-Control');
    $this->assertStringContainsString('no-cache', $cacheControl);
    $this->assertStringContainsString('no-transform', $cacheControl);
    $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    $this->assertTrue($captured['stream'] ?? FALSE);
    $this->assertSame('Bearer jwt-token', $captured['headers']['Authorization']);
    $this->assertSame('text/event-stream', $captured['headers']['Accept']);
  }

  public function testStreamChatBlocksNonElevatedRole(): void {
    // Defense-in-depth: even if 'use jarvis chat' permission ever leaks
    // to a non-elevated role, the controller refuses to open the upstream
    // stream. Asserting $http->expects($this->never())->method('request')
    // proves the upstream is never touched on the denied path.
    $http = $this->createMock(ClientInterface::class);
    $http->expects($this->never())->method('request');
    $controller = $this->makeController($http, NULL, ['authenticated']);
    $request = Request::create(
      '/api/jarvis/chat/stream', 'POST', [], [], [], [],
      json_encode(['messages' => [['role' => 'user', 'content' => 'q']]])
    );

    $response = $controller->streamChat($request);

    $this->assertSame(403, $response->getStatusCode());
  }

  public function testStreamChatRejectsEmptyBody(): void {
    $http = $this->createMock(ClientInterface::class);
    $http->expects($this->never())->method('request');
    $controller = $this->makeController($http);
    $request = Request::create(
      '/api/jarvis/chat/stream', 'POST', [], [], [], [], json_encode([])
    );

    $response = $controller->streamChat($request);

    $this->assertSame(400, $response->getStatusCode());
  }

}
