<?php

declare(strict_types=1);

namespace Drupal\Tests\jarvis_chat\Unit\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\jarvis_chat\Service\JarvisJwtSigner;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\jarvis_chat\Service\JarvisJwtSigner
 * @group jarvis_chat
 */
class JarvisJwtSignerTest extends UnitTestCase {

  private function makeSigner(string $uid): JarvisJwtSigner {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    return new JarvisJwtSigner($account);
  }

  protected function setUp(): void {
    parent::setUp();
    putenv('CHAT_JWT_SECRET');
  }

  protected function tearDown(): void {
    parent::tearDown();
    putenv('CHAT_JWT_SECRET');
  }

  public function testMintReturnsNullWhenSecretNotSet(): void {
    $token = $this->makeSigner('42')->mint();
    $this->assertNull($token);
  }

  public function testMintReturnsNullWhenSecretIsEmpty(): void {
    putenv('CHAT_JWT_SECRET=');
    $token = $this->makeSigner('42')->mint();
    $this->assertNull($token);
  }

  public function testMintReturnsNullForNonNumericUserId(): void {
    putenv('CHAT_JWT_SECRET=supersecret');
    $token = $this->makeSigner('niet-numeriek')->mint();
    $this->assertNull($token);
  }

  public function testMintReturnsNullForEmptyUserId(): void {
    putenv('CHAT_JWT_SECRET=supersecret');
    $token = $this->makeSigner('')->mint();
    $this->assertNull($token);
  }

  public function testMintProducesThreePartToken(): void {
    putenv('CHAT_JWT_SECRET=supersecret');
    $token = $this->makeSigner('7')->mint();
    $this->assertNotNull($token);
    $this->assertSame(3, substr_count($token, '.') + 1, 'JWT moet 3 delen hebben gescheiden door punten');
  }

  public function testMintedTokenHeaderIsHs256(): void {
    putenv('CHAT_JWT_SECRET=supersecret');
    $token = $this->makeSigner('7')->mint();
    $parts = explode('.', $token);
    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), TRUE);
    $this->assertSame('HS256', $header['alg']);
    $this->assertSame('JWT', $header['typ']);
  }

  public function testMintedTokenPayloadContainsSubAndScope(): void {
    putenv('CHAT_JWT_SECRET=supersecret');
    $token = $this->makeSigner('42')->mint('read+act');
    $parts = explode('.', $token);
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
    $this->assertSame('42', $payload['sub']);
    $this->assertSame('read+act', $payload['scope']);
    $this->assertArrayHasKey('exp', $payload);
  }

  public function testMintedTokenExpiresInApproximatelyOneHour(): void {
    putenv('CHAT_JWT_SECRET=supersecret');
    $before = time();
    $token = $this->makeSigner('1')->mint();
    $parts = explode('.', $token);
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
    $this->assertGreaterThanOrEqual($before + 3600, $payload['exp']);
    $this->assertLessThanOrEqual(time() + 3600, $payload['exp']);
  }

  public function testMintedTokenSignatureIsValid(): void {
    putenv('CHAT_JWT_SECRET=mijn-geheime-sleutel');
    $token = $this->makeSigner('5')->mint();
    $parts = explode('.', $token);
    $signingInput = $parts[0] . '.' . $parts[1];
    $expectedSig = rtrim(strtr(base64_encode(
      hash_hmac('sha256', $signingInput, 'mijn-geheime-sleutel', TRUE)
    ), '+/', '-_'), '=');
    $this->assertSame($expectedSig, $parts[2]);
  }

  public function testCustomTtlIsRespected(): void {
    putenv('CHAT_JWT_SECRET=supersecret');
    $before = time();
    $token = $this->makeSigner('3')->mint('read', 7200);
    $parts = explode('.', $token);
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
    $this->assertGreaterThanOrEqual($before + 7200, $payload['exp']);
  }

}
