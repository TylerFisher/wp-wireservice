<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Wireservice
 */

if (!defined("WP_UNINSTALL_PLUGIN")) {
  die();
}

// Clear scheduled events.
wp_clear_scheduled_hook("wireservice_refresh_token");

// Remove plugin options.
delete_option("wireservice_connection");
delete_option("wireservice_client_id");
delete_option("wireservice_client_secret");
delete_option("wireservice_oauth_url");
delete_option("wireservice_publication");
delete_option("wireservice_publication_uri");
delete_option("wireservice_db_version");
delete_option("wireservice_pub_settings");
delete_option("wireservice_doc_settings");

// Remove transients.
delete_transient("wireservice_code_verifier");
delete_transient("wireservice_oauth_state");

// Remove post meta for documents.
global $wpdb;
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_document_uri"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_title_source"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_description_source"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_image_source"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_custom_title"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_custom_description"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_custom_image_id"]);
$wpdb->delete($wpdb->postmeta, ["meta_key" => "_wireservice_include_content"]);

// Remove any custom tables.
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wireservice_logs");
