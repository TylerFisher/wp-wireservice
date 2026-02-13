<?php
declare(strict_types=1);
/**
 * Admin functionality for Wireservice.
 *
 * @package Wireservice
 */

namespace Wireservice;

class Admin
{
  /**
   * Constructor.
   */
  public function __construct(
    private ConnectionsManager $connections_manager,
    private API $api,
    private Publication $publication,
    private Document $document,
  ) {}

  /**
   * Initialize admin hooks.
   *
   * @return void
   */
  public function init(): void
  {
    add_action("admin_menu", [$this, "add_admin_menu"]);
    add_action("admin_init", [$this, "register_settings"]);
    add_action("admin_enqueue_scripts", [$this, "enqueue_settings_assets"]);
    add_action("admin_post_wireservice_sync_publication", [
      $this,
      "handle_sync_publication",
    ]);
    add_action("admin_post_wireservice_reset_data", [
      $this,
      "handle_reset_data",
    ]);
    add_action("admin_post_wireservice_save_doc_settings", [
      $this,
      "handle_save_doc_settings",
    ]);
    add_action("wp_ajax_wireservice_backfill_count", [
      $this,
      "handle_backfill_count",
    ]);
    add_action("wp_ajax_wireservice_backfill_batch", [
      $this,
      "handle_backfill_batch",
    ]);
    add_filter("plugin_action_links_wireservice/wireservice.php", [
      $this,
      "add_settings_link",
    ]);
  }

  /**
   * Enqueue scripts for the settings page.
   *
   * @param string $hook_suffix The current admin page hook suffix.
   * @return void
   */
  public function enqueue_settings_assets(string $hook_suffix): void
  {
    if ($hook_suffix !== "settings_page_wireservice") {
      return;
    }

    wp_enqueue_media();
    wp_enqueue_style("wp-color-picker");

    wp_enqueue_script(
      "wireservice-settings",
      WIRESERVICE_PLUGIN_URL . "assets/js/settings.js",
      ["jquery", "wp-color-picker"],
      WIRESERVICE_VERSION,
      true,
    );

    wp_localize_script("wireservice-settings", "wireserviceBackfill", [
      "ajaxUrl" => admin_url("admin-ajax.php"),
      "nonce" => wp_create_nonce("wireservice_backfill"),
    ]);
  }

  /**
   * Add admin menu page.
   *
   * @return void
   */
  public function add_admin_menu(): void
  {
    add_options_page(
      __("Wireservice", "wireservice"),
      __("Wireservice", "wireservice"),
      "manage_options",
      "wireservice",
      [$this, "render_settings_page"],
    );
  }

  /**
   * Register plugin settings.
   *
   * @return void
   */
  public function register_settings(): void
  {
    register_setting("wireservice", "wireservice_connection", [
      "type" => "object",
      "sanitize_callback" => [$this, "sanitize_connection"],
      "show_in_rest" => false,
      "default" => [],
    ]);

    register_setting("wireservice", "wireservice_client_id", [
      "type" => "string",
      "sanitize_callback" => "sanitize_text_field",
      "show_in_rest" => false,
      "default" => "",
    ]);

    register_setting("wireservice", "wireservice_client_secret", [
      "type" => "string",
      "sanitize_callback" => "sanitize_text_field",
      "show_in_rest" => false, // Don't expose secret via REST.
      "default" => "",
    ]);

    register_setting("wireservice", "wireservice_oauth_url", [
      "type" => "string",
      "sanitize_callback" => "esc_url_raw",
      "show_in_rest" => false,
      "default" => "https://aip.wireservice.net",
    ]);

    register_setting("wireservice", "wireservice_pub_settings", [
      "type" => "array",
      "sanitize_callback" => [$this, "sanitize_pub_settings"],
      "show_in_rest" => false,
      "default" => SourceOptions::PUB_DEFAULTS,
    ]);

    register_setting("wireservice", "wireservice_doc_settings", [
      "type" => "array",
      "sanitize_callback" => [$this, "sanitize_doc_settings"],
      "show_in_rest" => false,
      "default" => SourceOptions::DOC_DEFAULTS,
    ]);
  }

  /**
   * Sanitize connection data.
   *
   * @param mixed $value The value to sanitize.
   * @return array
   */
  public function sanitize_connection($value): array
  {
    if (!is_array($value)) {
      return [];
    }

    return [
      "access_token" => isset($value["access_token"])
        ? sanitize_text_field($value["access_token"])
        : "",
      "refresh_token" => isset($value["refresh_token"])
        ? sanitize_text_field($value["refresh_token"])
        : "",
      "expires_at" => isset($value["expires_at"])
        ? absint($value["expires_at"])
        : 0,
      "handle" => isset($value["handle"])
        ? sanitize_text_field($value["handle"])
        : "",
      "did" => isset($value["did"]) ? sanitize_text_field($value["did"]) : "",
    ];
  }

  /**
   * Sanitize publication settings.
   *
   * @param mixed $value The value to sanitize.
   * @return array
   */
  public function sanitize_pub_settings($value): array
  {
    if (!is_array($value)) {
      return SourceOptions::PUB_DEFAULTS;
    }

    return [
      "name_source" => isset($value["name_source"])
        ? sanitize_text_field($value["name_source"])
        : "wordpress_title",
      "description_source" => isset($value["description_source"])
        ? sanitize_text_field($value["description_source"])
        : "wordpress_tagline",
      "custom_name" => isset($value["custom_name"])
        ? sanitize_text_field($value["custom_name"])
        : "",
      "custom_description" => isset($value["custom_description"])
        ? sanitize_textarea_field($value["custom_description"])
        : "",
      "icon_source" => isset($value["icon_source"])
        ? sanitize_text_field($value["icon_source"])
        : "none",
      "custom_icon_id" => isset($value["custom_icon_id"])
        ? absint($value["custom_icon_id"])
        : 0,
      "theme_background" => isset($value["theme_background"])
        ? sanitize_hex_color($value["theme_background"]) ?: ""
        : "",
      "theme_foreground" => isset($value["theme_foreground"])
        ? sanitize_hex_color($value["theme_foreground"]) ?: ""
        : "",
      "theme_accent" => isset($value["theme_accent"])
        ? sanitize_hex_color($value["theme_accent"]) ?: ""
        : "",
      "theme_accent_foreground" => isset($value["theme_accent_foreground"])
        ? sanitize_hex_color($value["theme_accent_foreground"]) ?: ""
        : "",
      "show_in_discover" => isset($value["show_in_discover"])
        ? sanitize_text_field($value["show_in_discover"])
        : "",
    ];
  }

  /**
   * Sanitize document settings.
   *
   * @param mixed $value The value to sanitize.
   * @return array
   */
  public function sanitize_doc_settings($value): array
  {
    if (!is_array($value)) {
      return SourceOptions::DOC_DEFAULTS;
    }

    return [
      "enabled" => isset($value["enabled"])
        ? sanitize_text_field($value["enabled"])
        : "0",
      "title_source" => isset($value["title_source"])
        ? sanitize_text_field($value["title_source"])
        : "wordpress_title",
      "description_source" => isset($value["description_source"])
        ? sanitize_text_field($value["description_source"])
        : "wordpress_excerpt",
      "image_source" => isset($value["image_source"])
        ? sanitize_text_field($value["image_source"])
        : "wordpress_featured",
      "include_content" => isset($value["include_content"])
        ? sanitize_text_field($value["include_content"])
        : "0",
    ];
  }

  /**
   * Render the settings page.
   *
   * @return void
   */
  public function render_settings_page(): void
  {
    if (!current_user_can("manage_options")) {
      return;
    }

    $connection = get_option("wireservice_connection", []);
    $is_connected = !empty($connection["access_token"]);

    // Connection data.
    $session = null;
    $authorize_url = "";
    $oauth_url = "";
    $client_error = "";

    if ($is_connected) {
      $session = $this->api->get_session();
    } else {
      $authorize_url = $this->connections_manager->get_authorize_url();
      $oauth_url = get_option("wireservice_oauth_url");
      $client_error = get_transient("wireservice_client_error");
    }

    // Publication and document data (only needed when connected).
    $pub_uri = "";
    $yoast_active = false;
    $name_source = "wordpress_title";
    $desc_source = "wordpress_tagline";
    $custom_name = "";
    $custom_description = "";
    $name_sources = [];
    $desc_sources = [];
    $doc_title_source = "wordpress_title";
    $doc_desc_source = "wordpress_excerpt";
    $doc_image_source = "wordpress_featured";
    $doc_include_content = "0";
    $doc_enabled = "0";
    $doc_title_sources = [];
    $doc_desc_sources = [];
    $doc_image_sources = [];
    $icon_source = "none";
    $custom_icon_id = 0;
    $icon_preview_url = "";
    $icon_sources = [];
    $theme_background = "";
    $theme_foreground = "";
    $theme_accent = "";
    $theme_accent_foreground = "";
    $show_in_discover = "";

    if ($is_connected) {
      $pub_uri = $this->publication->get_at_uri();
      $yoast_active = Yoast::is_active();

      $pub = SourceOptions::get_pub_settings();
      $name_source = $pub["name_source"];
      $desc_source = $pub["description_source"];
      $custom_name = $pub["custom_name"];
      $custom_description = $pub["custom_description"];
      $icon_source = $pub["icon_source"];
      $custom_icon_id = $pub["custom_icon_id"];
      $theme_background = $pub["theme_background"];
      $theme_foreground = $pub["theme_foreground"];
      $theme_accent = $pub["theme_accent"];
      $theme_accent_foreground = $pub["theme_accent_foreground"];
      $show_in_discover = $pub["show_in_discover"];

      if ($icon_source === "custom" && $custom_icon_id) {
        $icon_preview_url = wp_get_attachment_image_url(
          $custom_icon_id,
          [64, 64],
        );
      } elseif ($icon_source === "wordpress_site_icon") {
        $icon_preview_url = get_site_icon_url(64);
      }

      $name_sources = SourceOptions::pub_name_sources($custom_name);
      $desc_sources = SourceOptions::pub_description_sources(
        $custom_description,
      );
      $icon_sources = SourceOptions::pub_icon_sources();

      $doc = SourceOptions::get_doc_settings();
      $doc_title_source = $doc["title_source"];
      $doc_desc_source = $doc["description_source"];
      $doc_image_source = $doc["image_source"];
      $doc_include_content = $doc["include_content"];
      $doc_enabled = $doc["enabled"];

      $doc_title_sources = SourceOptions::doc_title_sources();
      $doc_desc_sources = SourceOptions::doc_description_sources();
      $doc_image_sources = SourceOptions::doc_image_sources();
    }

    include WIRESERVICE_PLUGIN_DIR . "templates/settings-page.php";
  }

  /**
   * Add settings link to plugin actions.
   *
   * @param array $links Existing plugin action links.
   * @return array
   */
  public function add_settings_link(array $links): array
  {
    $settings_link = sprintf(
      '<a href="%s">%s</a>',
      esc_url(admin_url("options-general.php?page=wireservice")),
      esc_html__("Settings", "wireservice"),
    );
    array_unshift($links, $settings_link);
    return $links;
  }

  /**
   * Handle syncing the publication to ATProto.
   *
   * @return void
   */
  public function handle_sync_publication(): void
  {
    if (!current_user_can("manage_options")) {
      wp_die(
        esc_html__("You do not have permission to do this.", "wireservice"),
      );
    }

    check_admin_referer(
      "wireservice_sync_publication",
      "wireservice_pub_nonce",
    );

    $pub = SourceOptions::get_pub_settings();

    $pub["name_source"] = isset($_POST["wireservice_pub_name_source"])
      ? sanitize_text_field(wp_unslash($_POST["wireservice_pub_name_source"]))
      : $pub["name_source"];
    $pub["description_source"] = isset(
      $_POST["wireservice_pub_description_source"],
    )
      ? sanitize_text_field(
        wp_unslash($_POST["wireservice_pub_description_source"]),
      )
      : $pub["description_source"];

    if (isset($_POST["wireservice_pub_custom_name"])) {
      $pub["custom_name"] = sanitize_text_field(
        wp_unslash($_POST["wireservice_pub_custom_name"]),
      );
    }
    if (isset($_POST["wireservice_pub_custom_description"])) {
      $pub["custom_description"] = sanitize_textarea_field(
        wp_unslash($_POST["wireservice_pub_custom_description"]),
      );
    }

    $pub["icon_source"] = isset($_POST["wireservice_pub_icon_source"])
      ? sanitize_text_field(
        wp_unslash($_POST["wireservice_pub_icon_source"]),
      )
      : $pub["icon_source"];
    $pub["custom_icon_id"] = isset($_POST["wireservice_pub_custom_icon_id"])
      ? absint($_POST["wireservice_pub_custom_icon_id"])
      : $pub["custom_icon_id"];

    $pub["theme_background"] = isset(
      $_POST["wireservice_pub_theme_background"],
    )
      ? sanitize_hex_color(
        wp_unslash($_POST["wireservice_pub_theme_background"]),
      ) ?: ""
      : $pub["theme_background"];
    $pub["theme_foreground"] = isset(
      $_POST["wireservice_pub_theme_foreground"],
    )
      ? sanitize_hex_color(
        wp_unslash($_POST["wireservice_pub_theme_foreground"]),
      ) ?: ""
      : $pub["theme_foreground"];
    $pub["theme_accent"] = isset($_POST["wireservice_pub_theme_accent"])
      ? sanitize_hex_color(
        wp_unslash($_POST["wireservice_pub_theme_accent"]),
      ) ?: ""
      : $pub["theme_accent"];
    $pub["theme_accent_foreground"] = isset(
      $_POST["wireservice_pub_theme_accent_foreground"],
    )
      ? sanitize_hex_color(
        wp_unslash($_POST["wireservice_pub_theme_accent_foreground"]),
      ) ?: ""
      : $pub["theme_accent_foreground"];

    $pub["show_in_discover"] = isset(
      $_POST["wireservice_pub_show_in_discover"],
    )
      ? "1"
      : "0";

    update_option("wireservice_pub_settings", $pub);

    $name_source = $pub["name_source"];
    $desc_source = $pub["description_source"];

    $name = $this->resolve_publication_name($name_source);
    $description = $this->resolve_publication_description($desc_source);

    $icon_attachment_id = $this->resolve_publication_icon(
      $pub["icon_source"],
      $pub["custom_icon_id"],
    );

    $pub_data = [
      "url" => isset($_POST["wireservice_pub_url"])
        ? sanitize_url(wp_unslash($_POST["wireservice_pub_url"]))
        : home_url(),
      "name" => $name,
      "description" => $description,
      "icon_attachment_id" => $icon_attachment_id,
      "theme_background" => $pub["theme_background"],
      "theme_foreground" => $pub["theme_foreground"],
      "theme_accent" => $pub["theme_accent"],
      "theme_accent_foreground" => $pub["theme_accent_foreground"],
      "show_in_discover" => $pub["show_in_discover"],
    ];
    $this->publication->save_publication_data($pub_data);

    $result = $this->publication->sync_to_atproto($pub_data);

    if (is_wp_error($result)) {
      $error_data = $result->get_error_data();
      $debug_info = $result->get_error_message();
      if ($error_data) {
        $debug_info .= " (Data: " . wp_json_encode($error_data) . ")";
      }
      add_settings_error(
        "wireservice",
        "publication_sync_failed",
        sprintf(
          /* translators: %s: error message */
          __("Failed to sync publication: %s", "wireservice"),
          $debug_info,
        ),
        "error",
      );
    } else {
      add_settings_error(
        "wireservice",
        "publication_synced",
        __("Publication synced to AT Protocol.", "wireservice"),
        "success",
      );
    }

    set_transient("settings_errors", get_settings_errors(), 30);

    wp_safe_redirect(
      admin_url("options-general.php?page=wireservice&settings-updated=true"),
    );
    exit();
  }

  /**
   * Handle resetting all plugin data.
   *
   * @return void
   */
  public function handle_reset_data(): void
  {
    if (!current_user_can("manage_options")) {
      wp_die(
        esc_html__("You do not have permission to do this.", "wireservice"),
      );
    }

    check_admin_referer("wireservice_reset_data", "wireservice_reset_nonce");

    // Remove all plugin options.
    delete_option("wireservice_connection");
    delete_option("wireservice_client_id");
    delete_option("wireservice_client_secret");
    delete_option("wireservice_oauth_url");
    delete_option("wireservice_publication");
    delete_option("wireservice_publication_uri");
    delete_option("wireservice_pub_settings");
    delete_option("wireservice_doc_settings");

    // Remove transients.
    delete_transient("wireservice_code_verifier");
    delete_transient("wireservice_oauth_state");

    // Remove post meta for documents.
    global $wpdb;
    $wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_document_uri"]);
    $wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_title_source"]);
    $wpdb->delete($wpdb->postmeta, [
      "meta_key" => "_wireservice_description_source",
    ]);
    $wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_image_source"]);
    $wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_custom_title"]);
    $wpdb->delete($wpdb->postmeta, [
      "meta_key" => "_wireservice_custom_description",
    ]);
    $wpdb->delete($wpdb->postmeta, [
      "meta_key" => "_wireservice_custom_image_id",
    ]);
    $wpdb->delete($wpdb->postmeta, [
      "meta_key" => "_wireservice_include_content",
    ]);

    add_settings_error(
      "wireservice",
      "data_reset",
      __("All Wireservice data has been reset.", "wireservice"),
      "success",
    );

    set_transient("settings_errors", get_settings_errors(), 30);

    wp_safe_redirect(
      admin_url("options-general.php?page=wireservice&settings-updated=true"),
    );
    exit();
  }

  /**
   * Resolve the publication name based on the selected source.
   *
   * @param string $source The source key.
   * @return string
   */
  private function resolve_publication_name(string $source): string
  {
    $value = match ($source) {
      "yoast_organization" => Yoast::get_organization_name(),
      "yoast_website" => Yoast::get_website_name(),
      "custom" => SourceOptions::get_pub_settings()["custom_name"],
      default => get_bloginfo("name"),
    };

    return $value ?: get_bloginfo("name");
  }

  /**
   * Resolve the publication description based on the selected source.
   *
   * @param string $source The source key.
   * @return string
   */
  private function resolve_publication_description(string $source): string
  {
    $value = match ($source) {
      "yoast_homepage" => Yoast::get_homepage_description(),
      "custom" => SourceOptions::get_pub_settings()["custom_description"],
      default => get_bloginfo("description"),
    };

    return $value ?: get_bloginfo("description");
  }

  /**
   * Resolve the publication icon attachment ID based on the selected source.
   *
   * @param string $source         The source key.
   * @param int    $custom_icon_id The custom icon attachment ID.
   * @return int The attachment ID, or 0 if none.
   */
  private function resolve_publication_icon(
    string $source,
    int $custom_icon_id,
  ): int {
    return match ($source) {
      "wordpress_site_icon" => (int) get_option("site_icon", 0),
      "custom" => $custom_icon_id,
      default => 0,
    };
  }

  /**
   * Handle AJAX request to count unsynced posts for backfill.
   *
   * @return void
   */
  public function handle_backfill_count(): void
  {
    if (!current_user_can("manage_options")) {
      wp_send_json_error("Unauthorized.", 403);
    }

    check_ajax_referer("wireservice_backfill", "nonce");

    $post_types = apply_filters("wireservice_syncable_post_types", [
      "post",
      "page",
    ]);

    $query = new \WP_Query([
      "post_type" => $post_types,
      "post_status" => "publish",
      "posts_per_page" => -1,
      "fields" => "ids",
      "meta_query" => [
        [
          "key" => Document::META_KEY_URI,
          "compare" => "NOT EXISTS",
        ],
      ],
    ]);

    wp_send_json_success([
      "total" => $query->found_posts,
      "post_ids" => $query->posts,
    ]);
  }

  /**
   * Handle AJAX request to sync a batch of posts for backfill.
   *
   * @return void
   */
  public function handle_backfill_batch(): void
  {
    if (!current_user_can("manage_options")) {
      wp_send_json_error("Unauthorized.", 403);
    }

    check_ajax_referer("wireservice_backfill", "nonce");

    $post_ids = isset($_POST["post_ids"])
      ? array_map("absint", (array) $_POST["post_ids"])
      : [];

    if (empty($post_ids)) {
      wp_send_json_error("No post IDs provided.");
    }

    $results = [];
    foreach ($post_ids as $post_id) {
      $post = get_post($post_id);

      if (!$post || !$this->document->should_sync($post)) {
        $results[] = [
          "id" => $post_id,
          "success" => false,
          "error" => __("Post not eligible for sync.", "wireservice"),
        ];
        continue;
      }

      $response = $this->document->sync_to_atproto($post);

      if (is_wp_error($response)) {
        $results[] = [
          "id" => $post_id,
          "title" => get_the_title($post),
          "success" => false,
          "error" => $response->get_error_message(),
        ];
      } else {
        $results[] = [
          "id" => $post_id,
          "title" => get_the_title($post),
          "success" => true,
        ];
      }
    }

    wp_send_json_success(["results" => $results]);
  }

  /**
   * Handle saving document settings.
   *
   * @return void
   */
  public function handle_save_doc_settings(): void
  {
    if (!current_user_can("manage_options")) {
      wp_die(
        esc_html__("You do not have permission to do this.", "wireservice"),
      );
    }

    check_admin_referer(
      "wireservice_save_doc_settings",
      "wireservice_doc_nonce",
    );

    $doc = SourceOptions::get_doc_settings();

    $doc["enabled"] = isset($_POST["wireservice_doc_enabled"]) ? "1" : "0";

    if (isset($_POST["wireservice_doc_title_source"])) {
      $doc["title_source"] = sanitize_text_field(
        wp_unslash($_POST["wireservice_doc_title_source"]),
      );
    }

    if (isset($_POST["wireservice_doc_description_source"])) {
      $doc["description_source"] = sanitize_text_field(
        wp_unslash($_POST["wireservice_doc_description_source"]),
      );
    }

    if (isset($_POST["wireservice_doc_image_source"])) {
      $doc["image_source"] = sanitize_text_field(
        wp_unslash($_POST["wireservice_doc_image_source"]),
      );
    }

    $doc["include_content"] = isset($_POST["wireservice_doc_include_content"])
      ? "1"
      : "0";

    update_option("wireservice_doc_settings", $doc);

    add_settings_error(
      "wireservice",
      "doc_settings_saved",
      __("Document settings saved.", "wireservice"),
      "success",
    );

    set_transient("settings_errors", get_settings_errors(), 30);

    wp_safe_redirect(
      admin_url("options-general.php?page=wireservice&settings-updated=true"),
    );
    exit();
  }
}
