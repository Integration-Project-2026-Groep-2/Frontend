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
final class JarvisJwtSigner {

  public function __construct(private readonly AccountInterface $currentUser) {
  }

  public function mint(string $scope = 'read+act', int $ttlSeconds = 3600): ?string {
    $secret = (string) (getenv('CHAT_JWT_SECRET') ?: '');
    if ($secret === '') {
      return NULL;
    }
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
      'sub' => (string) $this->currentUser->id(),
      'scope' => $scope,
      'exp' => time() + $ttlSeconds,
    ];
    $signingInput = self::b64url(json_encode($header, JSON_UNESCAPED_SLASHES))
      . '.' . self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $signingInput, $secret, TRUE);
    return $signingInput . '.' . self::b64url($signature);
  }

  private static function b64url(string $input): string {
    return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
  }

}
