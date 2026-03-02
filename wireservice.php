<?php
declare(strict_types=1);
/**
 * Plugin Name: Wireservice
 * Plugin URI: https://wordpress.wireservice.net
 * Description: A WordPress plugin for publishing posts to the AT Protocol based on the standard.site lexicon.
 * Version: 1.1.0
 * Author: Tyler Fisher
 * Author URI: https://tylerjfisher.com
 * License: AGPL-3.0+
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Requires at least: 6.7
 * Requires PHP: 8.4
 * Text Domain: wireservice
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
  die();
}

// Minimum WordPress version.
define("WIRESERVICE_MIN_WP_VERSION", "6.7");

// Check WordPress version.
global $wp_version;
if (version_compare($wp_version, WIRESERVICE_MIN_WP_VERSION, "<")) {
  add_action("admin_notices", function () {
    printf(
      '<div class="notice notice-error"><p>%s</p></div>',
      sprintf(
        /* translators: %s: minimum WordPress version */
        esc_html__(
          "Wireservice requires WordPress %s or higher.",
          "wireservice",
        ),
        esc_html(WIRESERVICE_MIN_WP_VERSION),
      ),
    );
  });
  return;
}

// Plugin version.
define("WIRESERVICE_VERSION", "1.1.0");

// Plugin directory path.
define("WIRESERVICE_PLUGIN_DIR", plugin_dir_path(__FILE__));

// Plugin directory URL.
define("WIRESERVICE_PLUGIN_URL", plugin_dir_url(__FILE__));

// Load Composer autoloader.
if (file_exists(WIRESERVICE_PLUGIN_DIR . "vendor/autoload.php")) {
  require_once WIRESERVICE_PLUGIN_DIR . "vendor/autoload.php";
}

/**
 * Code that runs during plugin activation.
 */
function wireservice_activate()
{
  // Register rewrite rules.
  $setup = new Wireservice\Setup();
  $setup->register_well_known_rewrite();

  // Flush rewrite rules.
  flush_rewrite_rules();

  // Schedule token refresh cron event.
  if (!wp_next_scheduled("wireservice_refresh_token")) {
    wp_schedule_event(time(), "twicedaily", "wireservice_refresh_token");
  }
}
register_activation_hook(__FILE__, "wireservice_activate");

/**
 * Code that runs during plugin deactivation.
 */
function wireservice_deactivate()
{
  // Flush rewrite rules to remove our custom rules.
  flush_rewrite_rules();

  // Clear scheduled token refresh.
  wp_clear_scheduled_hook("wireservice_refresh_token");
}
register_deactivation_hook(__FILE__, "wireservice_deactivate");

/**
 * Initialize the plugin.
 */
function wireservice_init()
{
  $setup = new Wireservice\Setup();
  $setup->init();
}
add_action("plugins_loaded", "wireservice_init");
