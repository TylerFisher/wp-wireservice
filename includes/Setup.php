<?php
declare(strict_types=1);
/**
 * Plugin setup and initialization.
 *
 * @package Wireservice
 */

namespace Wireservice;

if (! defined('ABSPATH')) {
  exit;
}

class Setup
{
  private ConnectionsManager $connections_manager;
  private API $api;
  private Publication $publication;
  private Document $document;

  /**
   * Initialize the plugin.
   *
   * @return void
   */
  public function init(): void
  {
    $this->connections_manager = new ConnectionsManager();
    $this->api = new API($this->connections_manager);
    $this->publication = new Publication($this->api);
    $this->document = new Document($this->api, $this->publication);
    $this->register_hooks();
  }

  /**
   * Register all hooks and initialize components.
   *
   * @return void
   */
  private function register_hooks(): void
  {
    $admin = new Admin(
      $this->connections_manager,
      $this->api,
      $this->publication,
      $this->document,
    );
    $admin->init();

    // Initialize connections manager.
    $this->connections_manager->init();

    // Register REST API endpoints.
    add_action("rest_api_init", [$this, "register_rest_routes"]);

    // Register .well-known endpoint.
    add_action("init", [$this, "register_well_known_rewrite"]);
    add_action("template_redirect", [$this, "handle_well_known_request"]);
    add_filter("query_vars", [$this, "add_query_vars"]);

    // Scheduled token refresh.
    add_action("wireservice_refresh_token", [$this, "handle_scheduled_refresh"]);
    $this->ensure_cron_scheduled();

    // Document sync hooks.
    // Use wp_after_insert_post (WP 5.6+) to ensure all meta (including Yoast) is saved first.
    add_action("wp_after_insert_post", [$this, "maybe_sync_document"], 10, 2);
    add_action("before_delete_post", [$this, "maybe_delete_document"]);
    add_action(
      "transition_post_status",
      [$this, "handle_status_transition"],
      10,
      3,
    );

    // Add verification link to document head.
    add_action("wp_head", [$this, "output_document_verification_link"]);

    // Add meta box for per-post overrides.
    add_action("add_meta_boxes", [$this, "add_document_meta_box"]);
    add_action("save_post", [$this, "save_document_meta_box"], 10, 2);
    add_action("admin_enqueue_scripts", [$this, "enqueue_meta_box_assets"]);
  }

  /**
   * Ensure the token refresh cron event is scheduled.
   *
   * Covers upgrades from older versions where activation won't re-run.
   *
   * @return void
   */
  private function ensure_cron_scheduled(): void
  {
    if (
      $this->connections_manager->is_connected() &&
      !wp_next_scheduled("wireservice_refresh_token")
    ) {
      wp_schedule_event(time(), "twicedaily", "wireservice_refresh_token");
    }
  }

  /**
   * Handle the scheduled token refresh.
   *
   * @return void
   */
  public function handle_scheduled_refresh(): void
  {
    if (!$this->connections_manager->is_connected()) {
      return;
    }

    $this->connections_manager->get_access_token();
  }

  /**
   * Register REST API routes.
   *
   * @return void
   */
  public function register_rest_routes(): void
  {
    $connections_controller = new Endpoints\ConnectionsController(
      $this->connections_manager,
      $this->api,
    );
    $connections_controller->register_routes();
  }

  /**
   * Register rewrite rule for .well-known/site.standard.publication.
   *
   * For non-root sites, appends the site path per the standard.site spec:
   * .well-known/site.standard.publication/path/to/site
   *
   * @return void
   */
  public function register_well_known_rewrite(): void
  {
    $path = wp_parse_url(home_url(), PHP_URL_PATH);
    $suffix = '';

    if (!empty($path) && $path !== '/') {
      $suffix = '/' . trim($path, '/');
    }

    add_rewrite_rule(
      '^\.well-known/site\.standard\.publication' . preg_quote($suffix) . '$',
      "index.php?wireservice_well_known=publication",
      "top",
    );
  }

  /**
   * Add custom query vars.
   *
   * @param array $vars Existing query vars.
   * @return array Modified query vars.
   */
  public function add_query_vars(array $vars): array
  {
    $vars[] = "wireservice_well_known";
    return $vars;
  }

  /**
   * Handle .well-known request.
   *
   * @return void
   */
  public function handle_well_known_request(): void
  {
    $well_known = get_query_var("wireservice_well_known");

    if ("publication" !== $well_known) {
      return;
    }

    $at_uri = $this->publication->get_at_uri();

    if (empty($at_uri)) {
      status_header(404);
      echo esc_html("Publication not found");
      exit();
    }

    status_header(200);
    header("Content-Type: text/plain; charset=utf-8");
    header("Access-Control-Allow-Origin: *");
    echo esc_html($at_uri);
    exit();
  }

  /**
   * Maybe sync a document when a post is saved.
   *
   * @param int      $post_id The post ID.
   * @param \WP_Post $post    The post object.
   * @return void
   */
  public function maybe_sync_document(int $post_id, \WP_Post $post): void
  {
    // Bail if autosave or revision.
    if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
      return;
    }

    if (wp_is_post_revision($post_id)) {
      return;
    }

    // Bail if not connected.
    if (!$this->connections_manager->is_connected()) {
      return;
    }

    if (!$this->document->should_sync($post)) {
      return;
    }

    $this->document->sync_to_atproto($post);
  }

  /**
   * Maybe delete a document when a post is deleted.
   *
   * @param int $post_id The post ID.
   * @return void
   */
  public function maybe_delete_document(int $post_id): void
  {
    // Bail if not connected.
    if (!$this->connections_manager->is_connected()) {
      return;
    }

    $this->document->delete_from_atproto($post_id);
  }

  /**
   * Handle post status transitions (e.g., publish to draft).
   *
   * @param string   $new_status New post status.
   * @param string   $old_status Old post status.
   * @param \WP_Post $post       The post object.
   * @return void
   */
  public function handle_status_transition(
    string $new_status,
    string $old_status,
    \WP_Post $post,
  ): void {
    // Bail if not connected.
    if (!$this->connections_manager->is_connected()) {
      return;
    }

    if ($old_status === "publish" && $new_status !== "publish") {
      $this->document->delete_from_atproto($post->ID);
    }
  }

  /**
   * Output the document verification link tag in the head.
   *
   * @return void
   */
  public function output_document_verification_link(): void
  {
    if (!is_singular()) {
      return;
    }

    $post = get_queried_object();

    if (!$post instanceof \WP_Post) {
      return;
    }

    $at_uri = $this->document->get_at_uri($post->ID);

    if (empty($at_uri)) {
      return;
    }

    echo '<link rel="site.standard.document" href="' .
      esc_attr($at_uri) .
      '">' .
      "\n";
  }

  /**
   * Enqueue scripts for the document meta box.
   *
   * @param string $hook_suffix The current admin page hook suffix.
   * @return void
   */
  public function enqueue_meta_box_assets(string $hook_suffix): void
  {
    if (!in_array($hook_suffix, ["post.php", "post-new.php"], true)) {
      return;
    }

    if (!$this->connections_manager->is_connected()) {
      return;
    }

    global $post_type;
    $syncable_types = apply_filters("wireservice_syncable_post_types", [
      "post",
      "page",
    ]);
    if (!in_array($post_type, $syncable_types, true)) {
      return;
    }

    wp_enqueue_media();

    wp_enqueue_script(
      "wireservice-meta-box",
      WIRESERVICE_PLUGIN_URL . "assets/js/meta-box.js",
      [],
      WIRESERVICE_VERSION,
      true,
    );

    wp_localize_script("wireservice-meta-box", "wireserviceMetaBox", [
      "selectImageTitle" => __("Select Cover Image", "wireservice"),
      "useImageButton" => __("Use this image", "wireservice"),
    ]);
  }

  /**
   * Add the document settings meta box.
   *
   * @return void
   */
  public function add_document_meta_box(): void
  {
    if (!$this->connections_manager->is_connected()) {
      return;
    }

    $post_types = apply_filters("wireservice_syncable_post_types", [
      "post",
      "page",
    ]);

    foreach ($post_types as $post_type) {
      add_meta_box(
        "wireservice_document",
        __("Wireservice", "wireservice"),
        [$this, "render_document_meta_box"],
        $post_type,
        "side",
        "default",
      );
    }
  }

  /**
   * Render the document settings meta box.
   *
   * @param \WP_Post $post The post object.
   * @return void
   */
  public function render_document_meta_box(\WP_Post $post): void
  {
    wp_nonce_field("wireservice_document_meta", "wireservice_document_nonce");

    $title_source = get_post_meta($post->ID, "_wireservice_title_source", true);
    $desc_source = get_post_meta(
      $post->ID,
      "_wireservice_description_source",
      true,
    );
    $custom_title = get_post_meta($post->ID, "_wireservice_custom_title", true);
    $custom_description = get_post_meta(
      $post->ID,
      "_wireservice_custom_description",
      true,
    );
    $custom_image_id = get_post_meta(
      $post->ID,
      "_wireservice_custom_image_id",
      true,
    );
    $image_source = get_post_meta($post->ID, "_wireservice_image_source", true);
    $include_content = get_post_meta(
      $post->ID,
      "_wireservice_include_content",
      true,
    );

    $doc = SourceOptions::get_doc_settings();

    $title_sources = SourceOptions::meta_box_title_sources(
      $doc["title_source"],
    );
    $desc_sources = SourceOptions::meta_box_description_sources(
      $doc["description_source"],
    );
    $image_sources = SourceOptions::meta_box_image_sources(
      $doc["image_source"],
    );

    $global_label = $doc["include_content"] === "1"
      ? __("On", "wireservice")
      : __("Off", "wireservice");

    $at_uri = $this->document->get_at_uri($post->ID);

    include WIRESERVICE_PLUGIN_DIR . "templates/document-meta-box.php";
  }

  /**
   * Save the document meta box settings.
   *
   * @param int      $post_id The post ID.
   * @param \WP_Post $post    The post object.
   * @return void
   */
  public function save_document_meta_box(int $post_id, \WP_Post $post): void
  {
    if (
      !isset($_POST["wireservice_document_nonce"]) ||
      !wp_verify_nonce(
        sanitize_text_field(wp_unslash($_POST["wireservice_document_nonce"])),
        "wireservice_document_meta",
      )
    ) {
      return;
    }

    if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
      return;
    }

    if (!current_user_can("edit_post", $post_id)) {
      return;
    }

    if (isset($_POST["wireservice_title_source"])) {
      $this->save_meta_field($post_id, "_wireservice_title_source", sanitize_text_field(wp_unslash($_POST["wireservice_title_source"])));
    }
    if (isset($_POST["wireservice_description_source"])) {
      $this->save_meta_field($post_id, "_wireservice_description_source", sanitize_text_field(wp_unslash($_POST["wireservice_description_source"])));
    }
    if (isset($_POST["wireservice_image_source"])) {
      $this->save_meta_field($post_id, "_wireservice_image_source", sanitize_text_field(wp_unslash($_POST["wireservice_image_source"])));
    }
    if (isset($_POST["wireservice_custom_title"])) {
      $this->save_meta_field($post_id, "_wireservice_custom_title", sanitize_text_field(wp_unslash($_POST["wireservice_custom_title"])));
    }
    if (isset($_POST["wireservice_custom_description"])) {
      $this->save_meta_field($post_id, "_wireservice_custom_description", sanitize_textarea_field(wp_unslash($_POST["wireservice_custom_description"])));
    }
    if (isset($_POST["wireservice_custom_image_id"])) {
      $this->save_meta_field($post_id, "_wireservice_custom_image_id", absint(wp_unslash($_POST["wireservice_custom_image_id"])));
    }
    if (isset($_POST["wireservice_include_content"])) {
      $this->save_meta_field($post_id, "_wireservice_include_content", sanitize_text_field(wp_unslash($_POST["wireservice_include_content"])), allow_falsy: true);
    }
  }

  /**
   * Save or delete a single post meta field.
   *
   * @param int           $post_id     The post ID.
   * @param string        $meta_key    The post meta key.
   * @param string|int    $value       The sanitized value.
   * @param bool          $allow_falsy Whether to store falsy values like "0".
   * @return void
   */
  private function save_meta_field(
    int $post_id,
    string $meta_key,
    string|int $value,
    bool $allow_falsy = false,
  ): void {
    $is_empty = $allow_falsy ? $value === "" : empty($value);

    if ($is_empty) {
      delete_post_meta($post_id, $meta_key);
    } else {
      update_post_meta($post_id, $meta_key, $value);
    }
  }
}
