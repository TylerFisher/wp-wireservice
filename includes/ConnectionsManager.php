<?php
declare(strict_types=1);
/**
 * Manages OAuth connections to the AT Protocol service.
 *
 * @package Wireservice
 */

namespace Wireservice;

if (! defined('ABSPATH')) {
  exit;
}

class ConnectionsManager
{
  /**
   * OAuth service base URL.
   *
   * @var string
   */
  private string $oauth_url;

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->oauth_url = rtrim(get_option("wireservice_oauth_url", "https://aip.wireservice.net"), "/");
  }

  /**
   * Initialize the connections manager.
   *
   * @return void
   */
  public function init(): void
  {
    add_action("admin_post_wireservice_disconnect", [
      $this,
      "handle_disconnect",
    ]);
    add_action("admin_init", [$this, "handle_oauth_callback"]);
  }

  /**
   * Get the OAuth authorization URL.
   *
   * @return string
   */
  public function get_authorize_url(): string
  {
    if (empty($this->oauth_url)) {
      return "";
    }

    $client_id = $this->get_or_create_client_id();

    if (empty($client_id)) {
      return "";
    }

    // Generate PKCE code verifier and challenge.
    $code_verifier = $this->generate_code_verifier();
    $code_challenge = $this->generate_code_challenge($code_verifier);

    // Store verifier in transient for later use.
    set_transient("wireservice_code_verifier", $code_verifier, HOUR_IN_SECONDS);

    // Generate state for CSRF protection.
    $state = wp_generate_password(32, false);
    set_transient("wireservice_oauth_state", $state, HOUR_IN_SECONDS);

    // Store a WordPress nonce for callback verification.
    set_transient(
      "wireservice_oauth_nonce",
      wp_create_nonce("wireservice_oauth_callback"),
      HOUR_IN_SECONDS,
    );

    $params = [
      "client_id" => $client_id,
      "redirect_uri" => $this->get_redirect_uri(),
      "state" => $state,
      "code_challenge" => $code_challenge,
      "code_challenge_method" => "S256",
      "scope" =>
        "atproto repo:site.standard.publication repo:site.standard.document blob:*/*",
    ];

    return $this->oauth_url . "/oauth/authorize?" . http_build_query($params);
  }

  /**
   * Get or create the OAuth client ID.
   *
   * @return string
   */
  private function get_or_create_client_id(): string
  {
    $client_id = get_option("wireservice_client_id");

    if (!empty($client_id)) {
      return $client_id;
    }

    if (empty($this->oauth_url)) {
      return "";
    }

    // Register a new client via dynamic client registration.
    $response = wp_remote_post($this->oauth_url . "/oauth/clients/register", [
      "headers" => ["Content-Type" => "application/json"],
      "body" => wp_json_encode([
        "redirect_uris" => [$this->get_redirect_uri()],
        "client_name" => "Wireservice",
        "grant_types" => ["authorization_code", "refresh_token"],
        "response_types" => ["code"],
        "token_endpoint_auth_method" => "none",
      ]),
      "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
      set_transient(
        "wireservice_client_error",
        $response->get_error_message(),
        60,
      );
      return "";
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code >= 400 || empty($body["client_id"])) {
      $error_msg = $body["error"] ?? $body["message"] ?? "Unknown error";
      $error_desc = $body["error_description"] ?? "";
      $full_error = "HTTP $status_code: $error_msg";
      if ($error_desc) {
        $full_error .= " - $error_desc";
      }
      $full_error .= " (redirect_uri: " . $this->get_redirect_uri() . ")";
      set_transient("wireservice_client_error", $full_error, 60);
      return "";
    }

    // Clear any previous error.
    delete_transient("wireservice_client_error");

    update_option("wireservice_client_id", $body["client_id"]);
    return $body["client_id"];
  }

  /**
   * Get the OAuth redirect URI.
   *
   * @return string
   */
  public function get_redirect_uri(): string
  {
    return admin_url("options-general.php?page=wireservice");
  }

  /**
   * Generate a PKCE code verifier.
   *
   * @return string
   */
  private function generate_code_verifier(): string
  {
    $random_bytes = random_bytes(32);
    return rtrim(strtr(base64_encode($random_bytes), "+/", "-_"), "=");
  }

  /**
   * Generate a PKCE code challenge from a verifier.
   *
   * @param string $verifier The code verifier.
   * @return string
   */
  private function generate_code_challenge(string $verifier): string
  {
    $hash = hash("sha256", $verifier, true);
    return rtrim(strtr(base64_encode($hash), "+/", "-_"), "=");
  }

  /**
   * Handle the OAuth callback.
   *
   * @return void
   */
  public function handle_oauth_callback(): void
  {
    $page = filter_input(INPUT_GET, "page");
    if ($page !== "wireservice") {
      return;
    }

    $code = filter_input(INPUT_GET, "code");
    $state = filter_input(INPUT_GET, "state");
    if ($code === null || $state === null) {
      return;
    }

    // Verify user permissions and the stored WordPress nonce.
    if (!current_user_can("manage_options")) {
      return;
    }

    $stored_nonce = get_transient("wireservice_oauth_nonce");
    if (!$stored_nonce || !wp_verify_nonce($stored_nonce, "wireservice_oauth_callback")) {
      add_settings_error(
        "wireservice",
        "invalid_nonce",
        __("Security check failed. Please try again.", "wireservice"),
        "error",
      );
      return;
    }

    delete_transient("wireservice_oauth_nonce");

    $code = sanitize_text_field($code);
    $state = sanitize_text_field($state);

    // Verify state.
    $stored_state = get_transient("wireservice_oauth_state");
    if (!$stored_state || !hash_equals($stored_state, $state)) {
      add_settings_error(
        "wireservice",
        "invalid_state",
        __("Invalid OAuth state. Please try again.", "wireservice"),
        "error",
      );
      return;
    }

    delete_transient("wireservice_oauth_state");

    // Get the code verifier.
    $code_verifier = get_transient("wireservice_code_verifier");
    if (!$code_verifier) {
      add_settings_error(
        "wireservice",
        "missing_verifier",
        __("OAuth session expired. Please try again.", "wireservice"),
        "error",
      );
      return;
    }

    delete_transient("wireservice_code_verifier");

    // Exchange code for tokens.
    $result = $this->exchange_code_for_tokens($code, $code_verifier);

    if (is_wp_error($result)) {
      add_settings_error(
        "wireservice",
        "token_exchange_failed",
        $result->get_error_message(),
        "error",
      );
      return;
    }

    add_settings_error(
      "wireservice",
      "connected",
      __("Successfully connected to AT Protocol.", "wireservice"),
      "success",
    );

    // Redirect to remove query params.
    wp_safe_redirect(
      admin_url("options-general.php?page=wireservice&connected=1"),
    );
    exit();
  }

  /**
   * Exchange an authorization code for tokens.
   *
   * @param string $code          The authorization code.
   * @param string $code_verifier The PKCE code verifier.
   * @return true|\WP_Error
   */
  private function exchange_code_for_tokens(string $code, string $code_verifier)
  {
    $client_id = get_option("wireservice_client_id");

    if (empty($client_id)) {
      return new \WP_Error(
        "no_client_id",
        __("No OAuth client ID configured.", "wireservice"),
      );
    }

    $response = wp_remote_post($this->oauth_url . "/oauth/token", [
      "headers" => ["Content-Type" => "application/x-www-form-urlencoded"],
      "body" => [
        "grant_type" => "authorization_code",
        "code" => $code,
        "client_id" => $client_id,
        "redirect_uri" => $this->get_redirect_uri(),
        "code_verifier" => $code_verifier,
      ],
      "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
      return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code !== 200) {
      $error_message =
        $body["error_description"] ??
        ($body["error"] ?? __("Token exchange failed.", "wireservice"));
      return new \WP_Error("token_exchange_failed", $error_message);
    }

    if (empty($body["access_token"])) {
      return new \WP_Error(
        "no_access_token",
        __("No access token received.", "wireservice"),
      );
    }

    // Fetch session info to get handle and DID.
    $session = $this->fetch_session($body["access_token"]);

    $refresh_token = $body["refresh_token"] ?? "";

    $connection = [
      "access_token" => Encryption::encrypt($body["access_token"]),
      "refresh_token" => !empty($refresh_token)
        ? Encryption::encrypt($refresh_token)
        : "",
      "expires_at" => time() + ($body["expires_in"] ?? 3600),
      "handle" => $session["handle"] ?? "",
      "did" => $session["did"] ?? "",
    ];

    update_option("wireservice_connection", $connection);

    return true;
  }

  /**
   * Fetch session info from the API.
   *
   * @param string $access_token The access token.
   * @return array
   */
  private function fetch_session(string $access_token): array
  {
    $response = wp_remote_get($this->oauth_url . "/api/atprotocol/session", [
      "headers" => [
        "Authorization" => "Bearer " . $access_token,
      ],
      "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
      return [];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return is_array($body) ? $body : [];
  }

  /**
   * Refresh the access token.
   *
   * @return true|\WP_Error
   */
  public function refresh_token()
  {
    $connection = get_option("wireservice_connection", []);

    if (empty($connection["refresh_token"])) {
      return new \WP_Error(
        "no_refresh_token",
        __("No refresh token available.", "wireservice"),
      );
    }

    // Decrypt the refresh token, falling back to raw value for unencrypted legacy data.
    $refresh_token = Encryption::decrypt($connection["refresh_token"]);
    if ($refresh_token === false) {
      $refresh_token = $connection["refresh_token"];
    }

    $client_id = get_option("wireservice_client_id");

    $response = wp_remote_post($this->oauth_url . "/oauth/token", [
      "headers" => ["Content-Type" => "application/x-www-form-urlencoded"],
      "body" => [
        "grant_type" => "refresh_token",
        "refresh_token" => $refresh_token,
        "client_id" => $client_id,
      ],
      "timeout" => 30,
    ]);

    if (is_wp_error($response)) {
      return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code >= 400 || empty($body["access_token"])) {
      // Clear the stale connection so the user can reconnect.
      delete_option("wireservice_connection");

      $error_message = $body["error_description"]
        ?? ($body["error"] ?? __("Token refresh failed.", "wireservice"));

      return new \WP_Error(
        "refresh_failed",
        sprintf(
          /* translators: %s: error detail from the server */
          __("Session expired: %s. Please reconnect your account.", "wireservice"),
          $error_message,
        ),
      );
    }

    $connection["access_token"] = Encryption::encrypt($body["access_token"]);
    $new_refresh = $body["refresh_token"] ?? null;
    $connection["refresh_token"] = $new_refresh !== null
      ? Encryption::encrypt($new_refresh)
      : $connection["refresh_token"];
    $connection["expires_at"] = time() + ($body["expires_in"] ?? 3600);

    update_option("wireservice_connection", $connection);

    return true;
  }

  /**
   * Get a valid access token, refreshing if necessary.
   *
   * @return string|\WP_Error
   */
  public function get_access_token()
  {
    $connection = get_option("wireservice_connection", []);

    if (empty($connection["access_token"])) {
      return new \WP_Error(
        "not_connected",
        __("Not connected to AT Protocol.", "wireservice"),
      );
    }

    // Check if token is expired (with 5 min buffer).
    if (
      !empty($connection["expires_at"]) &&
      $connection["expires_at"] < time() + 300
    ) {
      $result = $this->refresh_token();
      if (is_wp_error($result)) {
        return $result;
      }
      $connection = get_option("wireservice_connection", []);
    }

    // Decrypt the access token, falling back to raw value for unencrypted legacy data.
    $access_token = Encryption::decrypt($connection["access_token"]);
    if ($access_token === false) {
      $access_token = $connection["access_token"];
    }

    return $access_token;
  }

  /**
   * Handle disconnect request.
   *
   * @return void
   */
  public function handle_disconnect(): void
  {
    if (!current_user_can("manage_options")) {
      wp_die(
        esc_html__("You do not have permission to do this.", "wireservice"),
      );
    }

    check_admin_referer("wireservice_disconnect", "wireservice_nonce");

    delete_option("wireservice_connection");

    wp_safe_redirect(
      admin_url("options-general.php?page=wireservice&disconnected=1"),
    );
    exit();
  }

  /**
   * Check if connected to AT Protocol.
   *
   * @return bool
   */
  public function is_connected(): bool
  {
    $connection = get_option("wireservice_connection", []);
    return !empty($connection["access_token"]);
  }

  /**
   * Get the current connection info.
   *
   * @return array
   */
  public function get_connection(): array
  {
    return get_option("wireservice_connection", []);
  }
}
