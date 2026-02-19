<?php
declare(strict_types=1);
/**
 * API client for communicating with the OAuth service and PDS.
 *
 * @package Wireservice
 */

namespace Wireservice;

if (! defined('ABSPATH')) {
  exit;
}

class API
{
  /**
   * OAuth service base URL.
   *
   * @var string
   */
  private string $oauth_url;

  /**
   * Cached session data.
   *
   * @var array|null
   */
  private ?array $session = null;

  /**
   * Cached PDS credentials.
   *
   * @var array|null
   */
  private ?array $pds_credentials = null;

  /**
   * Constructor.
   */
  public function __construct(private ConnectionsManager $connections_manager)
  {
    $this->oauth_url = rtrim(get_option("wireservice_oauth_url", "https://aip.wireservice.net"), "/");
  }

  /**
   * Make an authenticated request to the OAuth service.
   *
   * @param string $method   HTTP method.
   * @param string $endpoint API endpoint.
   * @param array  $args     Request arguments.
   * @return array|\WP_Error
   */
  public function request(string $method, string $endpoint, array $args = [])
  {
    $access_token = $this->connections_manager->get_access_token();

    if (is_wp_error($access_token)) {
      return $access_token;
    }

    if (empty($this->oauth_url)) {
      return new \WP_Error(
        "no_oauth_url",
        __("OAuth service URL is not configured.", "wireservice"),
      );
    }

    $url = $this->oauth_url . $endpoint;

    $default_args = [
      "method" => $method,
      "headers" => [
        "Authorization" => "Bearer " . $access_token,
        "Content-Type" => "application/json",
      ],
      "timeout" => 30,
    ];

    $args = wp_parse_args($args, $default_args);

    if (!empty($args["body"]) && is_array($args["body"])) {
      $args["body"] = wp_json_encode($args["body"]);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
      return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    return $this->maybe_error($status_code, $body, "api_error", __("API request failed.", "wireservice"));
  }

  /**
   * Make an authenticated request to the user's PDS.
   *
   * @param string $method   HTTP method.
   * @param string $endpoint API endpoint (e.g., /xrpc/com.atproto.repo.createRecord).
   * @param array  $args     Request arguments.
   * @param string $nonce    Optional DPoP nonce from a previous request.
   * @return array|\WP_Error
   */
  public function pds_request(
    string $method,
    string $endpoint,
    array $args = [],
    ?string $nonce = null,
  ) {
    $original_args = $args;

    $credentials = $this->get_pds_credentials();
    if (is_wp_error($credentials)) {
      return $credentials;
    }

    $pds_endpoint = $credentials["pds_endpoint"];
    $pds_token = $credentials["pds_token"];
    $dpop_jwk = $credentials["dpop_jwk"];

    $url = rtrim($pds_endpoint, "/") . $endpoint;

    $content_type = $args["headers"]["Content-Type"] ?? "application/json";
    unset($args["headers"]);

    $headers = [
      "Authorization" => "DPoP " . $pds_token,
      "Content-Type" => $content_type,
    ];

    if (!empty($dpop_jwk)) {
      $dpop_proof = $this->generate_dpop_header($dpop_jwk, $method, $url, $pds_token, $nonce);
      if ($dpop_proof) {
        $headers["DPoP"] = $dpop_proof;
      }
    }

    $default_args = [
      "method" => $method,
      "headers" => $headers,
      "timeout" => 30,
    ];

    $args = wp_parse_args($args, $default_args);

    if (!empty($args["body"]) && is_array($args["body"])) {
      $args["body"] = wp_json_encode($args["body"]);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
      return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    $response_nonce = $this->store_response_nonce($response, $dpop_jwk, $url);

    if ($this->is_dpop_nonce_error($status_code, $body) && $response_nonce && $nonce === null) {
      return $this->pds_request($method, $endpoint, $original_args, $response_nonce);
    }

    return $this->maybe_error($status_code, $body, "pds_error", __("PDS request failed.", "wireservice"));
  }

  /**
   * Make a GET request to the OAuth service.
   *
   * @param string $endpoint API endpoint.
   * @param array  $params   Query parameters.
   * @return array|\WP_Error
   */
  public function get(string $endpoint, array $params = [])
  {
    if (!empty($params)) {
      $endpoint .= "?" . http_build_query($params);
    }

    return $this->request("GET", $endpoint);
  }

  /**
   * Make a POST request to the OAuth service.
   *
   * @param string $endpoint API endpoint.
   * @param array  $body     Request body.
   * @return array|\WP_Error
   */
  public function post(string $endpoint, array $body = [])
  {
    return $this->request("POST", $endpoint, ["body" => $body]);
  }

  /**
   * Make a GET request to the PDS.
   *
   * @param string $endpoint API endpoint.
   * @param array  $params   Query parameters.
   * @return array|\WP_Error
   */
  public function pds_get(string $endpoint, array $params = [])
  {
    if (!empty($params)) {
      $endpoint .= "?" . http_build_query($params);
    }

    return $this->pds_request("GET", $endpoint);
  }

  /**
   * Make a POST request to the PDS.
   *
   * @param string $endpoint API endpoint.
   * @param array  $body     Request body.
   * @return array|\WP_Error
   */
  public function pds_post(string $endpoint, array $body = [])
  {
    return $this->pds_request("POST", $endpoint, ["body" => $body]);
  }

  /**
   * Get session information from the OAuth service.
   *
   * @return array|\WP_Error
   */
  public function get_session()
  {
    if ($this->session !== null) {
      return $this->session;
    }

    $session = $this->get("/api/atprotocol/session");

    if (!is_wp_error($session)) {
      $this->session = $session;
    }

    return $session;
  }

  /**
   * Get the user's DID from the session.
   *
   * @return string|\WP_Error
   */
  public function get_did()
  {
    $session = $this->get_session();

    if (is_wp_error($session)) {
      return $session;
    }

    return $session["did"] ??
      new \WP_Error("no_did", __("DID not found in session.", "wireservice"));
  }

  /**
   * Get PDS credentials from session.
   *
   * @return array|\WP_Error Array with pds_endpoint, pds_token, dpop_jwk or error.
   */
  private function get_pds_credentials()
  {
    if ($this->pds_credentials !== null) {
      return $this->pds_credentials;
    }

    $session = $this->get_session();

    if (is_wp_error($session)) {
      return $session;
    }

    $pds_endpoint = $session["pds_endpoint"] ?? null;
    $pds_token = $session["access_token"] ?? null;
    $dpop_jwk = $session["dpop_jwk"] ?? null;

    if (empty($pds_endpoint) || empty($pds_token)) {
      return new \WP_Error(
        "no_pds",
        __("PDS endpoint or token not available.", "wireservice"),
      );
    }

    $this->pds_credentials = [
      "pds_endpoint" => $pds_endpoint,
      "pds_token" => $pds_token,
      "dpop_jwk" => $dpop_jwk,
    ];

    return $this->pds_credentials;
  }

  /**
   * Generate a DPoP proof header value.
   *
   * @param array       $dpop_jwk  The DPoP JWK.
   * @param string      $method    HTTP method.
   * @param string      $url       Full request URL.
   * @param string      $pds_token Access token.
   * @param string|null $nonce     Optional nonce.
   * @return string|null The DPoP proof or null.
   */
  private function generate_dpop_header(
    array $dpop_jwk,
    string $method,
    string $url,
    string $pds_token,
    ?string $nonce = null,
  ): ?string {
    $proof = DPoP::generate_proof($dpop_jwk, $method, $url, $nonce, $pds_token);
    return $proof !== false ? $proof : null;
  }

  /**
   * Store DPoP nonce from response if present.
   *
   * @param array|object $response  WordPress HTTP response.
   * @param array|null   $dpop_jwk  The DPoP JWK.
   * @param string       $url       Request URL.
   * @return string|null The nonce if present.
   */
  private function store_response_nonce($response, ?array $dpop_jwk, string $url): ?string
  {
    $nonce = wp_remote_retrieve_header($response, "dpop-nonce");
    if ($nonce && !empty($dpop_jwk)) {
      DPoP::store_nonce($dpop_jwk, $url, $nonce);
    }
    return $nonce ?: null;
  }

  /**
   * Check if response indicates a DPoP nonce error.
   *
   * @param int   $status_code HTTP status code.
   * @param array $body        Response body.
   * @return bool True if nonce error.
   */
  private function is_dpop_nonce_error(int $status_code, ?array $body): bool
  {
    return in_array($status_code, [400, 401], true) &&
      isset($body["error"]) &&
      $body["error"] === "use_dpop_nonce";
  }

  /**
   * Create a record on the PDS.
   *
   * @param string $collection The collection NSID.
   * @param array  $record     The record data.
   * @param string $rkey       Optional record key.
   * @return array|\WP_Error
   */
  public function create_record(
    string $collection,
    array $record,
    ?string $rkey = null,
  ) {
    $did = $this->get_did();

    if (is_wp_error($did)) {
      return $did;
    }

    $body = [
      "repo" => $did,
      "collection" => $collection,
      "record" => $record,
    ];

    if ($rkey !== null) {
      $body["rkey"] = $rkey;
    }

    return $this->pds_post("/xrpc/com.atproto.repo.createRecord", $body);
  }

  /**
   * Update a record on the PDS.
   *
   * @param string $collection The collection NSID.
   * @param string $rkey       The record key.
   * @param array  $record     The record data.
   * @return array|\WP_Error
   */
  public function put_record(string $collection, string $rkey, array $record)
  {
    $did = $this->get_did();

    if (is_wp_error($did)) {
      return $did;
    }

    return $this->pds_post("/xrpc/com.atproto.repo.putRecord", [
      "repo" => $did,
      "collection" => $collection,
      "rkey" => $rkey,
      "record" => $record,
    ]);
  }

  /**
   * Delete a record from the PDS.
   *
   * @param string $collection The collection NSID.
   * @param string $rkey       The record key.
   * @return array|\WP_Error
   */
  public function delete_record(string $collection, string $rkey)
  {
    $did = $this->get_did();

    if (is_wp_error($did)) {
      return $did;
    }

    return $this->pds_post("/xrpc/com.atproto.repo.deleteRecord", [
      "repo" => $did,
      "collection" => $collection,
      "rkey" => $rkey,
    ]);
  }

  /**
   * Get a single record from the PDS.
   *
   * @param string $collection The collection NSID.
   * @param string $rkey       The record key.
   * @return array|\WP_Error
   */
  public function get_record(string $collection, string $rkey)
  {
    $did = $this->get_did();

    if (is_wp_error($did)) {
      return $did;
    }

    return $this->pds_get("/xrpc/com.atproto.repo.getRecord", [
      "repo" => $did,
      "collection" => $collection,
      "rkey" => $rkey,
    ]);
  }

  /**
   * List records in a collection from the PDS.
   *
   * @param string      $collection The collection NSID.
   * @param int         $limit      Max records to return (default 50, max 100).
   * @param string|null $cursor     Pagination cursor.
   * @return array|\WP_Error
   */
  public function list_records(
    string $collection,
    int $limit = 50,
    ?string $cursor = null,
  ) {
    $did = $this->get_did();

    if (is_wp_error($did)) {
      return $did;
    }

    $params = [
      "repo" => $did,
      "collection" => $collection,
      "limit" => $limit,
    ];

    if ($cursor !== null) {
      $params["cursor"] = $cursor;
    }

    return $this->pds_get("/xrpc/com.atproto.repo.listRecords", $params);
  }

  /**
   * Upload a blob to the PDS.
   *
   * @param string $file_path The local file path.
   * @param string $mime_type The MIME type of the file.
   * @return array|\WP_Error The blob reference or error.
   */
  public function upload_blob(string $file_path, string $mime_type)
  {
    if (!file_exists($file_path)) {
      return new \WP_Error(
        "file_not_found",
        __("File not found.", "wireservice"),
      );
    }

    $file_contents = file_get_contents($file_path);
    if ($file_contents === false) {
      return new \WP_Error(
        "file_read_error",
        __("Could not read file.", "wireservice"),
      );
    }

    return $this->pds_request("POST", "/xrpc/com.atproto.repo.uploadBlob", [
      "body" => $file_contents,
      "headers" => ["Content-Type" => $mime_type],
      "timeout" => 60,
    ]);
  }

  /**
   * Build a WP_Error from a failed response, or return the decoded body.
   *
   * @param int        $status_code HTTP status code.
   * @param array|null $body        Decoded response body.
   * @param string     $error_code  WP_Error code.
   * @param string     $default_msg Fallback error message.
   * @return array|\WP_Error
   */
  private function maybe_error(
    int $status_code,
    ?array $body,
    string $error_code,
    string $default_msg,
  ) {
    if ($status_code >= 400) {
      $error_message = $body["error"]
        ?? ($body["message"] ?? $default_msg);
      return new \WP_Error($error_code, $error_message, [
        "status" => $status_code,
      ]);
    }

    return $body;
  }
}
