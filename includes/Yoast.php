<?php
declare(strict_types=1);
/**
 * Yoast SEO integration helper.
 *
 * @package Wireservice
 */

namespace Wireservice;

class Yoast
{
  /**
   * Check if Yoast SEO is active and available.
   *
   * @return bool
   */
  public static function is_active(): bool
  {
    return function_exists("YoastSEO") ||
      class_exists("WPSEO_Options") ||
      defined("WPSEO_VERSION");
  }

  /**
   * Get the organization/company name from Yoast settings.
   *
   * @return string|null
   */
  public static function get_organization_name(): ?string
  {
    if (!self::is_active()) {
      return null;
    }

    $options = get_option("wpseo_titles", []);

    if (($options["company_or_person"] ?? "") === "company") {
      $name = $options["company_name"] ?? "";
      if (!empty($name)) {
        return $name;
      }
    }

    return null;
  }

  /**
   * Get the website name from Yoast settings.
   *
   * @return string|null
   */
  public static function get_website_name(): ?string
  {
    if (!self::is_active()) {
      return null;
    }

    $options = get_option("wpseo_titles", []);
    $name = $options["website_name"] ?? "";

    return !empty($name) ? $name : null;
  }

  /**
   * Get the homepage meta description from Yoast.
   *
   * @return string|null
   */
  public static function get_homepage_description(): ?string
  {
    if (!self::is_active()) {
      return null;
    }

    // Try Surfaces API for front page.
    if (function_exists("YoastSEO")) {
      try {
        $front_page_id = get_option("page_on_front");
        if ($front_page_id) {
          $meta = \YoastSEO()->meta->for_post((int) $front_page_id);
          if ($meta && !empty($meta->description)) {
            return $meta->description;
          }
        }
      } catch (\Exception $e) {
        // Fall through.
      }
    }

    // Check wpseo_titles for homepage description.
    $options = get_option("wpseo_titles", []);
    $desc = $options["metadesc-home-wpseo"] ?? "";

    return !empty($desc) ? $desc : null;
  }

  /**
   * Get the SEO title for a post.
   *
   * @param int $post_id The post ID.
   * @return string|null
   */
  public static function get_post_title(int $post_id): ?string
  {
    if (!self::is_active()) {
      return null;
    }

    // Try Surfaces API (Yoast 14.0+).
    if (function_exists("YoastSEO")) {
      try {
        $meta = \YoastSEO()->meta->for_post($post_id);
        if ($meta && !empty($meta->title)) {
          // Strip site name suffix if present.
          $title = $meta->title;
          $site_name = get_bloginfo("name");
          $separators = [" - ", " | ", " – ", " — ", " • "];
          foreach ($separators as $sep) {
            if (str_ends_with($title, $sep . $site_name)) {
              $title = substr($title, 0, -strlen($sep . $site_name));
              break;
            }
          }
          return trim($title);
        }
      } catch (\Exception $e) {
        // Fall through.
      }
    }

    // Fall back to post meta.
    $meta_title = get_post_meta($post_id, "_yoast_wpseo_title", true);
    if (!empty($meta_title)) {
      $meta_title = str_replace(
        ["%%title%%", "%%sitename%%", "%%sep%%"],
        [get_the_title($post_id), get_bloginfo("name"), "-"],
        $meta_title,
      );
      return trim($meta_title);
    }

    return null;
  }

  /**
   * Get a Yoast Surfaces API property with post meta fallback.
   *
   * @param int    $post_id  The post ID.
   * @param string $property The Surfaces API property name.
   * @param string $meta_key The post meta key fallback.
   * @return string|null
   */
  private static function get_surfaces_value(
    int $post_id,
    string $property,
    string $meta_key,
  ): ?string {
    if (!self::is_active()) {
      return null;
    }

    if (function_exists("YoastSEO")) {
      try {
        $meta = \YoastSEO()->meta->for_post($post_id);
        if ($meta && !empty($meta->$property)) {
          return $meta->$property;
        }
      } catch (\Exception $e) {
        // Fall through.
      }
    }

    $value = get_post_meta($post_id, $meta_key, true);
    return !empty($value) ? $value : null;
  }

  /**
   * Get the meta description for a post.
   *
   * @param int $post_id The post ID.
   * @return string|null
   */
  public static function get_post_description(int $post_id): ?string
  {
    return self::get_surfaces_value($post_id, "description", "_yoast_wpseo_metadesc");
  }

  /**
   * Get the Open Graph (social) title for a post.
   *
   * @param int $post_id The post ID.
   * @return string|null
   */
  public static function get_post_social_title(int $post_id): ?string
  {
    return self::get_surfaces_value($post_id, "open_graph_title", "_yoast_wpseo_opengraph-title");
  }

  /**
   * Get the Open Graph (social) description for a post.
   *
   * @param int $post_id The post ID.
   * @return string|null
   */
  public static function get_post_social_description(int $post_id): ?string
  {
    return self::get_surfaces_value($post_id, "open_graph_description", "_yoast_wpseo_opengraph-description");
  }

  /**
   * Get the X (Twitter) title for a post.
   *
   * @param int $post_id The post ID.
   * @return string|null
   */
  public static function get_post_x_title(int $post_id): ?string
  {
    return self::get_surfaces_value($post_id, "twitter_title", "_yoast_wpseo_twitter-title");
  }

  /**
   * Get the X (Twitter) description for a post.
   *
   * @param int $post_id The post ID.
   * @return string|null
   */
  public static function get_post_x_description(int $post_id): ?string
  {
    return self::get_surfaces_value($post_id, "twitter_description", "_yoast_wpseo_twitter-description");
  }

  /**
   * Get the Open Graph (social) image attachment ID for a post.
   *
   * @param int $post_id The post ID.
   * @return int|null The attachment ID or null.
   */
  public static function get_post_social_image_id(int $post_id): ?int
  {
    if (!self::is_active()) {
      return null;
    }

    // Try Surfaces API (Yoast 14.0+).
    if (function_exists("YoastSEO")) {
      try {
        $meta = \YoastSEO()->meta->for_post($post_id);
        if ($meta && !empty($meta->open_graph_images)) {
          $images = $meta->open_graph_images;
          if (is_array($images) && !empty($images[0]["id"])) {
            return (int) $images[0]["id"];
          }
          // If we have a URL but no ID, try to find the attachment.
          if (is_array($images) && !empty($images[0]["url"])) {
            $attachment_id = attachment_url_to_postid($images[0]["url"]);
            if ($attachment_id) {
              return $attachment_id;
            }
          }
        }
      } catch (\Exception $e) {
        // Fall through.
      }
    }

    // Fall back to post meta.
    $image_id = get_post_meta($post_id, "_yoast_wpseo_opengraph-image-id", true);
    if (!empty($image_id)) {
      return (int) $image_id;
    }

    return null;
  }

  /**
   * Get the X (Twitter) image attachment ID for a post.
   *
   * @param int $post_id The post ID.
   * @return int|null The attachment ID or null.
   */
  public static function get_post_x_image_id(int $post_id): ?int
  {
    if (!self::is_active()) {
      return null;
    }

    // Try Surfaces API (Yoast 14.0+).
    if (function_exists("YoastSEO")) {
      try {
        $meta = \YoastSEO()->meta->for_post($post_id);
        if ($meta && !empty($meta->twitter_image)) {
          // twitter_image is a URL, try to find the attachment.
          $attachment_id = attachment_url_to_postid($meta->twitter_image);
          if ($attachment_id) {
            return $attachment_id;
          }
        }
      } catch (\Exception $e) {
        // Fall through.
      }
    }

    // Fall back to post meta.
    $image_id = get_post_meta($post_id, "_yoast_wpseo_twitter-image-id", true);
    if (!empty($image_id)) {
      return (int) $image_id;
    }

    return null;
  }
}
