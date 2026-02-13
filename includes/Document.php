<?php
declare(strict_types=1);
/**
 * Manages site.standard.document records for WordPress posts/pages.
 *
 * @package Wireservice
 */

namespace Wireservice;

class Document
{
  /**
   * The lexicon NSID for documents.
   *
   * @var string
   */
  public const LEXICON = "site.standard.document";

  /**
   * Post meta key for storing the AT-URI.
   *
   * @var string
   */
  public const META_KEY_URI = "_wireservice_document_uri";

  /**
   * Constructor.
   */
  public function __construct(
    private API $api,
    private Publication $publication,
  ) {}

  /**
   * Build a document record from a WordPress post.
   *
   * @param \WP_Post $post The WordPress post.
   * @return array The document record.
   */
  public function build_record(\WP_Post $post): array
  {
    $publication_uri = $this->publication->get_at_uri();
    $publication_data = $this->publication->get_publication_data();

    // Use AT-URI if available, otherwise fall back to publication URL.
    $site = $publication_uri ?: rtrim($publication_data["url"], "/");

    $record = [
      '$type' => self::LEXICON,
      "site" => $site,
      "title" => mb_substr($this->get_document_title($post), 0, 5000),
      "publishedAt" => get_the_date("c", $post),
    ];

    // Add path (relative URL).
    $permalink = get_permalink($post);
    $home_url = home_url();
    if (strpos($permalink, $home_url) === 0) {
      $path = substr($permalink, strlen($home_url));
      if (!empty($path) && $path[0] !== "/") {
        $path = "/" . $path;
      }
      if (!empty($path)) {
        $record["path"] = $path;
      }
    }

    // Add description based on configured source.
    $description = $this->get_document_description($post);
    if (!empty($description)) {
      $record["description"] = mb_substr($description, 0, 30000);
    }

    // Add plain text content if enabled.
    if ($this->should_include_content($post)) {
      $content = $post->post_content;
      if (!empty($content)) {
        $text_content = wp_strip_all_tags(strip_shortcodes($content));
        $text_content = html_entity_decode(
          $text_content,
          ENT_QUOTES,
          "UTF-8",
        );
        $text_content = preg_replace("/\s+/", " ", $text_content);
        $text_content = trim($text_content);
        if (!empty($text_content)) {
          $record["textContent"] = $text_content;
        }
      }
    }

    // Add tags from post tags and categories.
    $tags = $this->get_post_tags($post);
    if (!empty($tags)) {
      $record["tags"] = $tags;
    }

    // Add cover image if configured.
    $cover_image = $this->get_document_cover_image($post);
    if ($cover_image) {
      $record["coverImage"] = $cover_image;
    }

    // Add updatedAt if the post was modified after publication.
    $published = strtotime($post->post_date_gmt);
    $modified = strtotime($post->post_modified_gmt);
    if ($modified > $published) {
      $record["updatedAt"] = get_the_modified_date("c", $post);
    }

    return $record;
  }

  /**
   * Get tags from a post (combines post tags and categories).
   *
   * @param \WP_Post $post The WordPress post.
   * @return array Array of tag strings.
   */
  private function get_post_tags(\WP_Post $post): array
  {
    $tags = [];

    // Get post tags.
    $post_tags = get_the_tags($post->ID);
    if ($post_tags && !is_wp_error($post_tags)) {
      foreach ($post_tags as $tag) {
        $tags[] = mb_substr($tag->name, 0, 1280);
      }
    }

    // Get categories (excluding "Uncategorized").
    $categories = get_the_category($post->ID);
    if ($categories && !is_wp_error($categories)) {
      foreach ($categories as $category) {
        if (strtolower($category->name) !== "uncategorized") {
          $tags[] = mb_substr($category->name, 0, 1280);
        }
      }
    }

    return array_unique($tags);
  }

  /**
   * Check whether full content should be included for this post.
   *
   * @param \WP_Post $post The WordPress post.
   * @return bool True if full content should be included.
   */
  private function should_include_content(\WP_Post $post): bool
  {
    $override = get_post_meta($post->ID, "_wireservice_include_content", true);
    $doc = SourceOptions::get_doc_settings();
    $value = $override !== "" ? $override : $doc["include_content"];

    return $value === "1";
  }

  /**
   * Get the document title based on configured source and per-post override.
   *
   * @param \WP_Post $post The WordPress post.
   * @return string The document title.
   */
  private function get_document_title(\WP_Post $post): string
  {
    // Check for per-post override.
    $override = get_post_meta($post->ID, "_wireservice_title_source", true);
    $doc = SourceOptions::get_doc_settings();
    $source = !empty($override) ? $override : $doc["title_source"];

    $title = match ($source) {
      "yoast_title" => Yoast::get_post_title($post->ID),
      "yoast_social_title" => Yoast::get_post_social_title($post->ID),
      "yoast_x_title" => Yoast::get_post_x_title($post->ID),
      "custom" => get_post_meta($post->ID, "_wireservice_custom_title", true),
      default => get_the_title($post),
    };

    return $title ?: get_the_title($post);
  }

  /**
   * Get the document description based on configured source and per-post override.
   *
   * @param \WP_Post $post The WordPress post.
   * @return string The document description.
   */
  private function get_document_description(\WP_Post $post): string
  {
    // Check for per-post override.
    $override = get_post_meta($post->ID, "_wireservice_description_source", true);
    $doc = SourceOptions::get_doc_settings();
    $source = !empty($override) ? $override : $doc["description_source"];

    $description = match ($source) {
      "yoast_description" => Yoast::get_post_description($post->ID),
      "yoast_social_description" => Yoast::get_post_social_description($post->ID),
      "yoast_x_description" => Yoast::get_post_x_description($post->ID),
      "custom" => get_post_meta($post->ID, "_wireservice_custom_description", true),
      default => get_the_excerpt($post),
    };

    return $description ?: "";
  }

  /**
   * Get the document cover image blob reference based on configured source.
   *
   * @param \WP_Post $post The WordPress post.
   * @return array|null The blob reference or null.
   */
  private function get_document_cover_image(\WP_Post $post): ?array
  {
    // Check for per-post override.
    $override = get_post_meta($post->ID, "_wireservice_image_source", true);
    $doc = SourceOptions::get_doc_settings();
    $source = !empty($override) ? $override : $doc["image_source"];

    if ($source === "none") {
      return null;
    }

    $attachment_id = match ($source) {
      "yoast_social_image" => Yoast::get_post_social_image_id($post->ID),
      "yoast_x_image" => Yoast::get_post_x_image_id($post->ID),
      "custom" => (int) get_post_meta($post->ID, "_wireservice_custom_image_id", true) ?: null,
      default => (int) get_post_thumbnail_id($post->ID) ?: null,
    };

    if (empty($attachment_id)) {
      return null;
    }

    // Get the file path and MIME type.
    $file_path = get_attached_file($attachment_id);
    $mime_type = get_post_mime_type($attachment_id);

    if (empty($file_path) || !file_exists($file_path)) {
      return null;
    }

    // Check file size (must be less than 1MB).
    $file_size = filesize($file_path);

    if ($file_size > 1000000) {
      return null;
    }

    // Upload the blob.
    $blob_response = $this->api->upload_blob($file_path, $mime_type);

    if (is_wp_error($blob_response)) {
      return null;
    }

    if (empty($blob_response["blob"])) {
      return null;
    }

    return $blob_response["blob"];
  }

  /**
   * Get the stored AT-URI for a post's document record.
   *
   * @param int $post_id The WordPress post ID.
   * @return string|null The AT-URI or null.
   */
  public function get_at_uri(int $post_id): ?string
  {
    $uri = get_post_meta($post_id, self::META_KEY_URI, true);
    return !empty($uri) ? $uri : null;
  }

  /**
   * Save the AT-URI for a post's document record.
   *
   * @param int    $post_id The WordPress post ID.
   * @param string $uri     The AT-URI.
   * @return bool
   */
  public function save_at_uri(int $post_id, string $uri): bool
  {
    return (bool) update_post_meta($post_id, self::META_KEY_URI, $uri);
  }

  /**
   * Delete the AT-URI for a post's document record.
   *
   * @param int $post_id The WordPress post ID.
   * @return bool
   */
  public function delete_at_uri(int $post_id): bool
  {
    return delete_post_meta($post_id, self::META_KEY_URI);
  }

  /**
   * Sync a WordPress post to ATProto as a document.
   *
   * @param int|\WP_Post $post The post ID or WP_Post object.
   * @return array|\WP_Error The response or error.
   */
  public function sync_to_atproto($post)
  {
    if (is_int($post)) {
      $post = get_post($post);
    }

    if (!$post || !($post instanceof \WP_Post)) {
      return new \WP_Error(
        "invalid_post",
        __("Invalid post.", "wireservice"),
      );
    }

    // Only sync published posts.
    if ($post->post_status !== "publish") {
      return new \WP_Error(
        "not_published",
        __("Post is not published.", "wireservice"),
      );
    }

    $record = $this->build_record($post);
    $existing_uri = $this->get_at_uri($post->ID);

    if ($existing_uri) {
      // Update existing record.
      $rkey = AtUri::get_rkey($existing_uri);
      $response = $this->api->put_record(self::LEXICON, $rkey, $record);
    } else {
      // Create new record.
      $response = $this->api->create_record(self::LEXICON, $record);

      if (!is_wp_error($response) && !empty($response["uri"])) {
        $this->save_at_uri($post->ID, $response["uri"]);
      }
    }

    return $response;
  }

  /**
   * Delete a document record from ATProto.
   *
   * @param int $post_id The WordPress post ID.
   * @return array|\WP_Error|null The response, error, or null if no record exists.
   */
  public function delete_from_atproto(int $post_id)
  {
    $existing_uri = $this->get_at_uri($post_id);

    if (!$existing_uri) {
      return null;
    }

    $rkey = AtUri::get_rkey($existing_uri);
    $response = $this->api->delete_record(self::LEXICON, $rkey);

    if (!is_wp_error($response)) {
      $this->delete_at_uri($post_id);
    }

    return $response;
  }

  /**
   * Check if a post should be synced to ATProto.
   *
   * @param \WP_Post $post The WordPress post.
   * @return bool
   */
  public function should_sync(\WP_Post $post): bool
  {
    // Only sync posts and pages by default.
    $allowed_types = apply_filters("wireservice_syncable_post_types", [
      "post",
      "page",
    ]);

    if (!in_array($post->post_type, $allowed_types, true)) {
      return false;
    }

    // Only sync published content.
    if ($post->post_status !== "publish") {
      return false;
    }

    // Allow filtering.
    return apply_filters("wireservice_should_sync_post", true, $post);
  }
}
