<?php
declare(strict_types=1);
/**
 * DPoP (Demonstration of Proof of Possession) support using oauth2-dpop library.
 *
 * @package Wireservice
 */

namespace Wireservice;

if (! defined('ABSPATH')) {
  exit;
}

use danielburger1337\OAuth2\DPoP\DPoPProofFactory;
use danielburger1337\OAuth2\DPoP\Encoder\WebTokenFrameworkDPoPTokenEncoder;
use danielburger1337\OAuth2\DPoP\Model\AccessTokenModel;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Symfony\Component\Clock\NativeClock;

class DPoP
{
  /**
   * Supported algorithms for AT Protocol.
   *
   * @var array
   */
  private const SUPPORTED_ALGORITHMS = ["ES256"];

  /**
   * Cached factory instances keyed by JWK hash.
   *
   * @var array<string, DPoPProofFactory>
   */
  private static array $factories = [];

  /**
   * Cached JWK thumbprints keyed by JWK hash.
   *
   * @var array<string, string>
   */
  private static array $thumbprints = [];

  /**
   * Generate a DPoP proof JWT.
   *
   * @param array       $jwk          The JWK to sign with (must include private key 'd').
   * @param string      $method       The HTTP method (GET, POST, etc.).
   * @param string      $url          The full URL being requested.
   * @param string|null $nonce        Optional server-provided nonce.
   * @param string|null $access_token Optional access token for ath claim.
   * @return string|false The DPoP proof JWT or false on failure.
   */
  public static function generate_proof(
    array $jwk,
    string $method,
    string $url,
    ?string $nonce = null,
    ?string $access_token = null,
  ): string|false {
    try {
      $factory = self::get_factory($jwk);

      // Store nonce if provided (for the library to pick up).
      if ($nonce !== null) {
        self::store_nonce($jwk, $url, $nonce);
      }

      // Create the proof.
      $bindTo = null;
      if ($access_token !== null) {
        $thumbprint = self::get_thumbprint($jwk);
        $bindTo = new AccessTokenModel($access_token, $thumbprint);
      }

      $proof = $factory->createProof(
        strtoupper($method),
        $url,
        self::SUPPORTED_ALGORITHMS,
        $bindTo,
      );

      return $proof->proof;
    } catch (\Throwable $e) {
      error_log("DPoP proof generation failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Store a nonce received from a server response.
   *
   * @param array  $jwk   The JWK used in the request.
   * @param string $url   The request URL.
   * @param string $nonce The nonce from the response header.
   */
  public static function store_nonce(array $jwk, string $url, string $nonce): void
  {
    $factory = self::get_factory($jwk);
    $jwkInterface = $factory->getJwkToBind(self::SUPPORTED_ALGORITHMS);
    $factory->storeNextNonce($nonce, $jwkInterface, $url);
  }

  /**
   * Get or create a DPoPProofFactory for the given JWK.
   *
   * @param array $jwk The JWK array.
   * @return DPoPProofFactory The factory instance.
   */
  private static function get_factory(array $jwk): DPoPProofFactory
  {
    $key = md5(wp_json_encode($jwk));

    if (!isset(self::$factories[$key])) {
      $jwtJwk = new JWK($jwk);
      $algorithmManager = new AlgorithmManager([new ES256()]);
      $encoder = new WebTokenFrameworkDPoPTokenEncoder($jwtJwk, $algorithmManager);
      $nonceStorage = new NonceStorage();
      $clock = new NativeClock();

      self::$factories[$key] = new DPoPProofFactory(
        $clock,
        $encoder,
        $nonceStorage,
      );
    }

    return self::$factories[$key];
  }

  /**
   * Get the JWK thumbprint.
   *
   * @param array $jwk The JWK array.
   * @return string The thumbprint.
   */
  private static function get_thumbprint(array $jwk): string
  {
    $key = md5(wp_json_encode($jwk));

    if (!isset(self::$thumbprints[$key])) {
      $jwtJwk = new JWK($jwk);
      self::$thumbprints[$key] = $jwtJwk->thumbprint("sha256");
    }

    return self::$thumbprints[$key];
  }

}
