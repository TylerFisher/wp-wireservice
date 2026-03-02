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
delete_post_meta_by_key("_wireservice_document_uri");
delete_post_meta_by_key("_wireservice_title_source");
delete_post_meta_by_key("_wireservice_description_source");
delete_post_meta_by_key("_wireservice_image_source");
delete_post_meta_by_key("_wireservice_custom_title");
delete_post_meta_by_key("_wireservice_custom_description");
delete_post_meta_by_key("_wireservice_custom_image_id");
delete_post_meta_by_key("_wireservice_include_content");
