<?php
declare(strict_types=1);
/**
 * Manages site.standard.publication records.
 *
 * @package Wireservice
 */

namespace Wireservice;

if (! defined('ABSPATH')) {
  exit;
}

class Publication
{
  /**
   * The lexicon NSID for publications.
   *
   * @var string
   */
  public const LEXICON = "site.standard.publication";

  /**
   * Constructor.
   */
  public function __construct(private API $api) {}

  /**
   * Get the publication data from WordPress options.
   *
   * @return array
   */
  public function get_publication_data(): array
  {
    $default = [
      "url" => home_url(),
      "name" => get_bloginfo("name"),
      "description" => get_bloginfo("description"),
      "icon_attachment_id" => 0,
      "theme_background" => "",
      "theme_foreground" => "",
      "theme_accent" => "",
      "theme_accent_foreground" => "",
      "show_in_discover" => "",
    ];
    $data = get_option("wireservice_publication", $default);

    return is_array($data) ? wp_parse_args($data, $default) : $default;
  }

  /**
   * Save publication data to WordPress options.
   *
   * @param array $data Publication data.
   * @return bool
   */
  public function save_publication_data(array $data): bool
  {
    $sanitized = [
      "url" => esc_url_raw($data["url"] ?? home_url()),
      "name" => sanitize_text_field($data["name"] ?? ""),
      "description" => sanitize_textarea_field($data["description"] ?? ""),
      "icon_attachment_id" => absint($data["icon_attachment_id"] ?? 0),
      "theme_background" => sanitize_hex_color($data["theme_background"] ?? "") ?: "",
      "theme_foreground" => sanitize_hex_color($data["theme_foreground"] ?? "") ?: "",
      "theme_accent" => sanitize_hex_color($data["theme_accent"] ?? "") ?: "",
      "theme_accent_foreground" => sanitize_hex_color($data["theme_accent_foreground"] ?? "") ?: "",
      "show_in_discover" => sanitize_text_field($data["show_in_discover"] ?? ""),
    ];

    return update_option("wireservice_publication", $sanitized);
  }

  /**
   * Get the stored AT-URI for the publication record.
   *
   * @return string|null
   */
  public function get_at_uri(): ?string
  {
    return get_option("wireservice_publication_uri", null);
  }

  /**
   * Save the AT-URI for the publication record.
   *
   * @param string $uri The AT-URI.
   * @return bool
   */
  public function save_at_uri(string $uri): bool
  {
    return update_option("wireservice_publication_uri", $uri);
  }

  /**
   * Create or update the publication record on ATProto.
   *
   * @return array|\WP_Error The response or error.
   */
  public function sync_to_atproto(?array $data = null)
  {
    if ($data === null) {
      $data = $this->get_publication_data();
    }
    $existing_uri = $this->get_at_uri();

    $record = [
      '$type' => self::LEXICON,
      "url" => rtrim($data["url"], "/"),
      "name" => mb_substr($data["name"], 0, 5000),
    ];

    if (!empty($data["description"])) {
      $record["description"] = mb_substr($data["description"], 0, 30000);
    }

    // Add icon blob if an attachment is configured.
    $icon_blob = $this->upload_icon($data["icon_attachment_id"] ?? 0);
    if ($icon_blob) {
      $record["icon"] = $icon_blob;
    }

    // Add basicTheme if all 4 colors are set.
    $theme = $this->build_basic_theme($data);
    if ($theme) {
      $record["basicTheme"] = $theme;
    }

    // Add preferences if showInDiscover has been explicitly set.
    if ($data["show_in_discover"] !== "") {
      $record["preferences"] = [
        "showInDiscover" => $data["show_in_discover"] === "1",
      ];
    }

    if ($existing_uri) {
      $rkey = AtUri::get_rkey($existing_uri);
      $response = $this->api->put_record(self::LEXICON, $rkey, $record);
    } else {
      $response = $this->api->create_record(self::LEXICON, $record);

      if (!is_wp_error($response) && !empty($response["uri"])) {
        $this->save_at_uri($response["uri"]);
      }
    }

    return $response;
  }

  /**
   * Upload an icon image as a blob.
   *
   * @param int $attachment_id The WordPress attachment ID.
   * @return array|null The blob reference or null.
   */
  private function upload_icon(int $attachment_id): ?array
  {
    if (empty($attachment_id)) {
      return null;
    }

    $file_path = get_attached_file($attachment_id);
    $mime_type = get_post_mime_type($attachment_id);

    if (empty($file_path) || !file_exists($file_path)) {
      return null;
    }

    // Icons must be less than 1MB.
    if (filesize($file_path) > 1000000) {
      return null;
    }

    $blob_response = $this->api->upload_blob($file_path, $mime_type);

    if (is_wp_error($blob_response) || empty($blob_response["blob"])) {
      return null;
    }

    return $blob_response["blob"];
  }

  /**
   * Build a basicTheme record from hex color values.
   *
   * Returns null if any of the 4 required colors is missing.
   *
   * @param array $data Publication data with theme_* keys.
   * @return array|null The basicTheme record or null.
   */
  private function build_basic_theme(array $data): ?array
  {
    $keys = [
      "background" => "theme_background",
      "foreground" => "theme_foreground",
      "accent" => "theme_accent",
      "accentForeground" => "theme_accent_foreground",
    ];

    $colors = [];
    foreach ($keys as $field => $data_key) {
      $hex = $data[$data_key] ?? "";
      if (empty($hex)) {
        return null;
      }
      $colors[$field] = self::hex_to_rgb($hex);
    }

    return [
      '$type' => "site.standard.theme.basic",
      "background" => $colors["background"],
      "foreground" => $colors["foreground"],
      "accent" => $colors["accent"],
      "accentForeground" => $colors["accentForeground"],
    ];
  }

  /**
   * Convert a hex color string to an RGB array.
   *
   * @param string $hex Hex color (e.g. "#ff0000").
   * @return array RGB array with $type, r, g, b keys.
   */
  private static function hex_to_rgb(string $hex): array
  {
    $hex = ltrim($hex, "#");
    return [
      '$type' => "site.standard.theme.color#rgb",
      "r" => (int) hexdec(substr($hex, 0, 2)),
      "g" => (int) hexdec(substr($hex, 2, 2)),
      "b" => (int) hexdec(substr($hex, 4, 2)),
    ];
  }

  /**
   * Find an existing publication record on the PDS that matches this site's URL.
   *
   * @return array|null The matching record (uri, cid, value keys) or null.
   */
  public function find_matching_record(): ?array
  {
    $site_url = rtrim(home_url(), "/");
    $cursor = null;

    do {
      $result = $this->api->list_records(self::LEXICON, 100, $cursor);

      if (is_wp_error($result)) {
        return null;
      }

      foreach ($result["records"] ?? [] as $record) {
        $record_url = rtrim($record["value"]["url"] ?? "", "/");
        if ($record_url === $site_url) {
          return $record;
        }
      }

      $cursor = $result["cursor"] ?? null;
    } while ($cursor);

    return null;
  }

  /**
   * Adopt an existing publication record from the PDS.
   *
   * @param array $record The record array with uri, cid, and value keys.
   * @return void
   */
  public function adopt_record(array $record): void
  {
    if (empty($record["uri"]) || !is_string($record["uri"]) || !isset($record["value"])) {
      return;
    }

    $this->save_at_uri(sanitize_text_field($record["uri"]));

    $value = $record["value"];
    $theme = $value["basicTheme"] ?? null;

    // save_publication_data() sanitizes all values (esc_url_raw, sanitize_text_field,
    // sanitize_textarea_field, sanitize_hex_color, absint).
    $data = [
      "url" => $value["url"] ?? home_url(),
      "name" => $value["name"] ?? "",
      "description" => $value["description"] ?? "",
      "icon_attachment_id" => 0,
      "theme_background" => $theme ? self::rgb_to_hex($theme["background"] ?? null) : "",
      "theme_foreground" => $theme ? self::rgb_to_hex($theme["foreground"] ?? null) : "",
      "theme_accent" => $theme ? self::rgb_to_hex($theme["accent"] ?? null) : "",
      "theme_accent_foreground" => $theme ? self::rgb_to_hex($theme["accentForeground"] ?? null) : "",
      "show_in_discover" => isset($value["preferences"]["showInDiscover"])
        ? ($value["preferences"]["showInDiscover"] ? "1" : "0")
        : "",
    ];

    $this->save_publication_data($data);
  }

  /**
   * Convert an RGB color array to a hex color string.
   *
   * @param array|null $color RGB array with r, g, b keys.
   * @return string Hex color (e.g. "#ff0000") or empty string.
   */
  private static function rgb_to_hex(?array $color): string
  {
    if ($color === null || !isset($color["r"], $color["g"], $color["b"])) {
      return "";
    }

    $r = max(0, min(255, (int) $color["r"]));
    $g = max(0, min(255, (int) $color["g"]));
    $b = max(0, min(255, (int) $color["b"]));

    return sprintf("#%02x%02x%02x", $r, $g, $b);
  }

  /**
   * Delete the publication record from ATProto.
   *
   * @return array|\WP_Error|null The response, error, or null if no record exists.
   */
  public function delete_from_atproto()
  {
    $existing_uri = $this->get_at_uri();

    if (!$existing_uri) {
      return null;
    }

    $rkey = AtUri::get_rkey($existing_uri);
    $response = $this->api->delete_record(self::LEXICON, $rkey);

    if (!is_wp_error($response)) {
      delete_option("wireservice_publication_uri");
    }

    return $response;
  }

}
