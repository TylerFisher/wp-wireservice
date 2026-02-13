<?php
declare(strict_types=1);
/**
 * Utility for working with AT-URI strings.
 *
 * @package Wireservice
 */

namespace Wireservice;

class AtUri
{
  /**
   * Extract the rkey (last path segment) from an AT-URI.
   *
   * @param string $uri The AT-URI (e.g., at://did:plc:xxx/site.standard.document/rkey).
   * @return string The rkey.
   */
  public static function get_rkey(string $uri): string
  {
    $parts = explode("/", $uri);
    return end($parts);
  }
}
