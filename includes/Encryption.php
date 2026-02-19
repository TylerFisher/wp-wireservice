<?php
declare(strict_types=1);
/**
 * Token encryption at rest using libsodium.
 *
 * @package Wireservice
 */

namespace Wireservice;

if (! defined('ABSPATH')) {
  exit;
}

class Encryption
{
  /**
   * Derive an encryption key from WordPress auth constants.
   *
   * @return string 32-byte key.
   */
  private static function get_key(): string
  {
    return sodium_crypto_generichash(
      AUTH_KEY . AUTH_SALT,
      "",
      SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
    );
  }

  /**
   * Encrypt a plaintext string.
   *
   * @param string $plaintext The value to encrypt.
   * @return string Base64-encoded nonce + ciphertext.
   */
  public static function encrypt(string $plaintext): string
  {
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, self::get_key());

    return base64_encode($nonce . $ciphertext);
  }

  /**
   * Decrypt a previously encrypted string.
   *
   * Returns false if decryption fails, which also covers the case
   * where a plaintext (never-encrypted) value is passed in.
   *
   * @param string $encoded Base64-encoded nonce + ciphertext.
   * @return string|false The plaintext or false on failure.
   */
  public static function decrypt(string $encoded): string|false
  {
    $decoded = base64_decode($encoded, true);

    if ($decoded === false) {
      return false;
    }

    $nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

    if (strlen($decoded) < $nonce_length + 1) {
      return false;
    }

    $nonce = substr($decoded, 0, $nonce_length);
    $ciphertext = substr($decoded, $nonce_length);

    return sodium_crypto_secretbox_open($ciphertext, $nonce, self::get_key());
  }
}
