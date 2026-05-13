<?php

declare(strict_types=1);

namespace Drupal\jarvis_chat\Service;

use Drupal\Core\Session\AccountInterface;

/**
 * Mints HS256 JWTs for /chat/approve + /chat/reject calls to mcp-master.
 *
 * Manual hash_hmac implementation rather than firebase/php-jwt — that
 * dependency is currently blocked by composer audit (PKSA-y2cr-5h3j-g3ys);
 * HS256 sign-only is ~20 lines of base64url + HMAC-SHA256 with PHP built-ins.
 *
 * Mints with sub=$currentUser->id(), scope=read+act, exp=+1h.
 * Returns null when CHAT_JWT_SECRET is unset — caller (JarvisController)
 * forwards without Authorization header so mcp-master falls back cleanly.
 */
class JarvisJwtSigner {

  public function __construct(private readonly AccountInterface $currentUser) {
  }

  public function mint(string $scope = 'read+act', int $ttlSeconds = 3600): ?string {
    $secret = (string) (getenv('CHAT_JWT_SECRET') ?: '');
    if ($secret === '') {
      return NULL;
    }
    // Defense-in-depth: refuse to sign anything other than a numeric Drupal
    // uid. Drupal core always returns int but a future SSO/masquerade module
    // could return a non-numeric string that escapes the JSON shape.
    $sub = (string) $this->currentUser->id();
    if ($sub === '' || !ctype_digit($sub)) {
      return NULL;
    }
    // JSON_THROW_ON_ERROR turns silent encoding failures (which would
    // otherwise concatenate `false` into the signing input and produce a
    // signed-but-malformed token) into a JsonException the caller handles.
    $headerJson = json_encode(
      ['alg' => 'HS256', 'typ' => 'JWT'],
      JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
    $payloadJson = json_encode(
      ['sub' => $sub, 'scope' => $scope, 'exp' => time() + $ttlSeconds],
      JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
    $signingInput = self::b64url($headerJson) . '.' . self::b64url($payloadJson);
    $signature = hash_hmac('sha256', $signingInput, $secret, TRUE);
    return $signingInput . '.' . self::b64url($signature);
  }

  private static function b64url(string $input): string {
    return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
  }

}
