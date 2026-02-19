<?php
declare(strict_types=1);
/**
 * Centralized source option building and grouped settings accessors.
 *
 * @package Wireservice
 */

namespace Wireservice;

class SourceOptions
{
  /**
   * Default values for publication settings.
   *
   * @var array
   */
  public const PUB_DEFAULTS = [
    "name_source" => "wordpress_title",
    "description_source" => "wordpress_tagline",
    "custom_name" => "",
    "custom_description" => "",
    "icon_source" => "none",
    "custom_icon_id" => 0,
    "theme_background" => "",
    "theme_foreground" => "",
    "theme_accent" => "",
    "theme_accent_foreground" => "",
    "show_in_discover" => "",
  ];

  /**
   * Default values for document settings.
   *
   * @var array
   */
  public const DOC_DEFAULTS = [
    "enabled" => "0",
    "title_source" => "wordpress_title",
    "description_source" => "wordpress_excerpt",
    "image_source" => "wordpress_featured",
    "include_content" => "0",
  ];

  /**
   * All valid publication name source keys.
   *
   * @var string[]
   */
  private const VALID_PUB_NAME_KEYS = [
    "wordpress_title",
    "yoast_organization",
    "yoast_website",
    "custom",
  ];

  /**
   * All valid publication description source keys.
   *
   * @var string[]
   */
  private const VALID_PUB_DESC_KEYS = [
    "wordpress_tagline",
    "yoast_homepage",
    "custom",
  ];

  /**
   * All valid publication icon source keys.
   *
   * @var string[]
   */
  private const VALID_PUB_ICON_KEYS = [
    "none",
    "wordpress_site_icon",
    "custom",
  ];

  /**
   * All valid document title source keys.
   *
   * @var string[]
   */
  private const VALID_DOC_TITLE_KEYS = [
    "wordpress_title",
    "yoast_title",
    "yoast_social_title",
    "yoast_x_title",
    "custom",
  ];

  /**
   * All valid document description source keys.
   *
   * @var string[]
   */
  private const VALID_DOC_DESC_KEYS = [
    "wordpress_excerpt",
    "yoast_description",
    "yoast_social_description",
    "yoast_x_description",
    "custom",
  ];

  /**
   * All valid document image source keys.
   *
   * @var string[]
   */
  private const VALID_DOC_IMAGE_KEYS = [
    "none",
    "wordpress_featured",
    "yoast_social_image",
    "yoast_x_image",
    "custom",
  ];

  /**
   * Validate a publication name source key, returning the default if invalid.
   *
   * @param string $key The source key to validate.
   * @return string The validated key.
   */
  public static function validate_pub_name_key(string $key): string
  {
    return in_array($key, self::VALID_PUB_NAME_KEYS, true)
      ? $key
      : self::PUB_DEFAULTS["name_source"];
  }

  /**
   * Validate a publication description source key, returning the default if invalid.
   *
   * @param string $key The source key to validate.
   * @return string The validated key.
   */
  public static function validate_pub_desc_key(string $key): string
  {
    return in_array($key, self::VALID_PUB_DESC_KEYS, true)
      ? $key
      : self::PUB_DEFAULTS["description_source"];
  }

  /**
   * Validate a publication icon source key, returning the default if invalid.
   *
   * @param string $key The source key to validate.
   * @return string The validated key.
   */
  public static function validate_pub_icon_key(string $key): string
  {
    return in_array($key, self::VALID_PUB_ICON_KEYS, true)
      ? $key
      : self::PUB_DEFAULTS["icon_source"];
  }

  /**
   * Validate a document title source key, returning the default if invalid.
   *
   * @param string $key The source key to validate.
   * @return string The validated key.
   */
  public static function validate_doc_title_key(string $key): string
  {
    return in_array($key, self::VALID_DOC_TITLE_KEYS, true)
      ? $key
      : self::DOC_DEFAULTS["title_source"];
  }

  /**
   * Validate a document description source key, returning the default if invalid.
   *
   * @param string $key The source key to validate.
   * @return string The validated key.
   */
  public static function validate_doc_desc_key(string $key): string
  {
    return in_array($key, self::VALID_DOC_DESC_KEYS, true)
      ? $key
      : self::DOC_DEFAULTS["description_source"];
  }

  /**
   * Validate a document image source key, returning the default if invalid.
   *
   * @param string $key The source key to validate.
   * @return string The validated key.
   */
  public static function validate_doc_image_key(string $key): string
  {
    return in_array($key, self::VALID_DOC_IMAGE_KEYS, true)
      ? $key
      : self::DOC_DEFAULTS["image_source"];
  }

  /**
   * Get publication settings merged with defaults.
   *
   * @return array
   */
  public static function get_pub_settings(): array
  {
    return wp_parse_args(
      get_option("wireservice_pub_settings", []),
      self::PUB_DEFAULTS,
    );
  }

  /**
   * Get document settings merged with defaults.
   *
   * @return array
   */
  public static function get_doc_settings(): array
  {
    return wp_parse_args(
      get_option("wireservice_doc_settings", []),
      self::DOC_DEFAULTS,
    );
  }

  /**
   * Get publication name source options for the settings page.
   *
   * @param string $custom_name The current custom name value.
   * @return array
   */
  public static function pub_name_sources(string $custom_name = ""): array
  {
    $sources = [
      "wordpress_title" => [
        "label" => __("WordPress Site Title", "wireservice"),
        "value" => get_bloginfo("name"),
      ],
    ];

    if (Yoast::is_active()) {
      $yoast_org = Yoast::get_organization_name();
      if ($yoast_org) {
        $sources["yoast_organization"] = [
          "label" => __("Yoast Organization Name", "wireservice"),
          "value" => $yoast_org,
        ];
      }
      $yoast_site = Yoast::get_website_name();
      if ($yoast_site) {
        $sources["yoast_website"] = [
          "label" => __("Yoast Website Name", "wireservice"),
          "value" => $yoast_site,
        ];
      }
    }

    $sources["custom"] = [
      "label" => __("Custom", "wireservice"),
      "value" => $custom_name,
    ];

    return $sources;
  }

  /**
   * Get publication description source options for the settings page.
   *
   * @param string $custom_description The current custom description value.
   * @return array
   */
  public static function pub_description_sources(
    string $custom_description = "",
  ): array {
    $sources = [
      "wordpress_tagline" => [
        "label" => __("WordPress Tagline", "wireservice"),
        "value" => get_bloginfo("description"),
      ],
    ];

    if (Yoast::is_active()) {
      $yoast_desc = Yoast::get_homepage_description();
      if ($yoast_desc) {
        $sources["yoast_homepage"] = [
          "label" => __(
            "Yoast Homepage Meta Description",
            "wireservice",
          ),
          "value" => $yoast_desc,
        ];
      }
    }

    $sources["custom"] = [
      "label" => __("Custom", "wireservice"),
      "value" => $custom_description,
    ];

    return $sources;
  }

  /**
   * Get publication icon source options for the settings page.
   *
   * @return array
   */
  public static function pub_icon_sources(): array
  {
    $sources = [
      "none" => [
        "label" => __("None (no icon)", "wireservice"),
        "value" => "",
      ],
    ];

    $site_icon_url = get_site_icon_url(256);
    if ($site_icon_url) {
      $sources["wordpress_site_icon"] = [
        "label" => __("WordPress Site Icon", "wireservice"),
        "value" => $site_icon_url,
      ];
    }

    $sources["custom"] = [
      "label" => __("Custom", "wireservice"),
      "value" => "",
    ];

    return $sources;
  }

  /**
   * Get document title source options.
   *
   * @return array
   */
  public static function doc_title_sources(): array
  {
    $sources = [
      "wordpress_title" => __("WordPress Post Title", "wireservice"),
    ];

    if (Yoast::is_active()) {
      $sources["yoast_title"] = __("Yoast SEO Title", "wireservice");
      $sources["yoast_social_title"] = __(
        "Yoast Social Title",
        "wireservice",
      );
      $sources["yoast_x_title"] = __("Yoast X Title", "wireservice");
    }

    return $sources;
  }

  /**
   * Get document description source options.
   *
   * @return array
   */
  public static function doc_description_sources(): array
  {
    $sources = [
      "wordpress_excerpt" => __("WordPress Excerpt", "wireservice"),
    ];

    if (Yoast::is_active()) {
      $sources["yoast_description"] = __(
        "Yoast Meta Description",
        "wireservice",
      );
      $sources["yoast_social_description"] = __(
        "Yoast Social Description",
        "wireservice",
      );
      $sources["yoast_x_description"] = __(
        "Yoast X Description",
        "wireservice",
      );
    }

    return $sources;
  }

  /**
   * Get document cover image source options.
   *
   * @return array
   */
  public static function doc_image_sources(): array
  {
    $sources = [
      "none" => __("None (no cover image)", "wireservice"),
      "wordpress_featured" => __(
        "WordPress Featured Image",
        "wireservice",
      ),
    ];

    if (Yoast::is_active()) {
      $sources["yoast_social_image"] = __(
        "Yoast Social Image",
        "wireservice",
      );
      $sources["yoast_x_image"] = __("Yoast X Image", "wireservice");
    }

    return $sources;
  }

  /**
   * Get document title source options for the meta box (with "Use global" option).
   *
   * @param string $global_source The current global title source key.
   * @return array
   */
  public static function meta_box_title_sources(string $global_source): array
  {
    return self::build_meta_box_sources(
      self::doc_title_sources(),
      $global_source,
      __("WordPress Post Title", "wireservice"),
    );
  }

  /**
   * Get document description source options for the meta box (with "Use global" option).
   *
   * @param string $global_source The current global description source key.
   * @return array
   */
  public static function meta_box_description_sources(
    string $global_source,
  ): array {
    return self::build_meta_box_sources(
      self::doc_description_sources(),
      $global_source,
      __("WordPress Excerpt", "wireservice"),
    );
  }

  /**
   * Get document image source options for the meta box (with "Use global" option).
   *
   * @param string $global_source The current global image source key.
   * @return array
   */
  public static function meta_box_image_sources(
    string $global_source,
  ): array {
    return self::build_meta_box_sources(
      self::doc_image_sources(),
      $global_source,
      __("WordPress Featured Image", "wireservice"),
    );
  }

  /**
   * Build meta box source options with a "Use global setting" prefix.
   *
   * @param array  $base           Base source options from a doc_*_sources() method.
   * @param string $global_source  The current global source key.
   * @param string $fallback_label Label to use if $global_source is not in $base.
   * @return array
   */
  private static function build_meta_box_sources(
    array $base,
    string $global_source,
    string $fallback_label,
  ): array {
    $base["custom"] = __("Custom", "wireservice");

    $global_label = $base[$global_source] ?? $fallback_label;

    return [
      "" => sprintf(
        /* translators: %s: source name */
        __("Use global setting (%s)", "wireservice"),
        $global_label,
      ),
    ] + $base;
  }
}
