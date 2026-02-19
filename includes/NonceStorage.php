<?php
declare(strict_types=1);
/**
 * DPoP nonce storage using WordPress transients.
 *
 * @package Wireservice
 */

namespace Wireservice;

if (! defined('ABSPATH')) {
  exit;
}

use danielburger1337\OAuth2\DPoP\NonceStorage\NonceStorageInterface;

class NonceStorage implements NonceStorageInterface
{
  /**
   * Transient prefix.
   *
   * @var string
   */
  private const PREFIX = "wireservice_dpop_nonce_";

  /**
   * Nonce TTL in seconds.
   *
   * @var int
   */
  private const TTL = 300;

  /**
   * Get the current DPoP-Nonce for an upstream server.
   *
   * @param string $key The storage key.
   * @return string|null The nonce or null.
   */
  public function getCurrentNonce(string $key): ?string
  {
    $nonce = get_transient(self::PREFIX . $this->hashKey($key));
    return $nonce !== false ? $nonce : null;
  }

  /**
   * Store a new DPoP-Nonce from an upstream server.
   *
   * @param string $key   The storage key.
   * @param string $nonce The nonce to store.
   */
  public function storeNextNonce(string $key, string $nonce): void
  {
    set_transient(self::PREFIX . $this->hashKey($key), $nonce, self::TTL);
  }

  /**
   * Hash the key to a safe transient name.
   *
   * @param string $key The storage key.
   * @return string The hashed key.
   */
  private function hashKey(string $key): string
  {
    return substr(md5($key), 0, 32);
  }
}
