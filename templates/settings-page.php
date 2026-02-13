<?php
/**
 * Settings page template for Wireservice.
 *
 * @package Wireservice
 *
 * @var array  $connection
 * @var bool   $is_connected
 * @var array|\WP_Error|null $session
 * @var string $authorize_url
 * @var string $oauth_url
 * @var string $client_error
 * @var array  $name_sources
 * @var array  $desc_sources
 * @var string $name_source
 * @var string $desc_source
 * @var string $custom_name
 * @var string $custom_description
 * @var string $pub_uri
 * @var bool   $yoast_active
 * @var string $icon_source
 * @var int    $custom_icon_id
 * @var string $icon_preview_url
 * @var array  $icon_sources
 * @var string $theme_background
 * @var string $theme_foreground
 * @var string $theme_accent
 * @var string $theme_accent_foreground
 * @var string $show_in_discover
 * @var array  $doc_title_sources
 * @var array  $doc_desc_sources
 * @var array  $doc_image_sources
 * @var string $doc_title_source
 * @var string $doc_desc_source
 * @var string $doc_image_source
 * @var string $doc_include_content
 * @var string $doc_enabled
 */

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php $active_tab = isset($_GET["tab"]) ? sanitize_key($_GET["tab"]) : "settings"; ?>
    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url("options-general.php?page=wireservice&tab=settings")); ?>"
           class="nav-tab <?php echo $active_tab === "settings" ? "nav-tab-active" : ""; ?>">
            <?php esc_html_e("Settings", "wireservice"); ?>
        </a>
        <?php if ($is_connected && $pub_uri): ?>
        <a href="<?php echo esc_url(admin_url("options-general.php?page=wireservice&tab=records")); ?>"
           class="nav-tab <?php echo $active_tab === "records" ? "nav-tab-active" : ""; ?>">
            <?php esc_html_e("Records", "wireservice"); ?>
        </a>
        <?php endif; ?>
    </nav>

    <?php if ($active_tab === "settings"): ?>
    <div class="wireservice-settings">
        <h2><?php esc_html_e("AT Protocol Connection", "wireservice"); ?></h2>

        <?php if ($is_connected): ?>
            <div class="wireservice-connection-status connected">
                <?php if (!is_wp_error($session)): ?>
                    <div class="wireservice-profile">
                        <div class="wireservice-profile-info">
                            <strong class="wireservice-display-name">Connected to @<?php echo esc_html($session["handle"]); ?></strong>
                        </div>
                    </div>
                <?php
                /* translators: %s: user handle */
                else: ?>
                    <p>
                        <?php printf(
                          esc_html__("Connected as: %s", "wireservice"),
                          "<strong>" . esc_html($connection["handle"] ?? "Unknown") . "</strong>",
                        ); ?>
                    </p>
                    <p class="wireservice-error"><?php echo esc_html($session->get_error_message()); ?></p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>">
                    <?php wp_nonce_field("wireservice_disconnect", "wireservice_nonce"); ?>
                    <input type="hidden" name="action" value="wireservice_disconnect">
                    <button type="submit" class="button button-secondary">
                        <?php esc_html_e("Disconnect", "wireservice"); ?>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="wireservice-connection-status disconnected">
                <p><?php esc_html_e("Not connected to AT Protocol.", "wireservice"); ?></p>
                <?php if (!empty($authorize_url)): ?>
                    <a href="<?php echo esc_url($authorize_url); ?>" class="button button-primary">
                        <?php esc_html_e("Connect Account", "wireservice"); ?>
                    </a>
                <?php else: ?>
                    <?php if (empty($oauth_url)): ?>
                        <p class="wireservice-error">
                            <?php esc_html_e("Please configure the OAuth Service URL in settings below.", "wireservice"); ?>
                        </p>
                    <?php elseif ($client_error): ?>
                        <p class="wireservice-error">
                            <?php printf(
                              /* translators: %s: error message */
                              esc_html__("Failed to register with OAuth service: %s", "wireservice"),
                              esc_html($client_error),
                            ); ?>
                        </p>
                    <?php else: ?>
                        <p class="wireservice-error">
                            <?php esc_html_e("Unable to connect to OAuth service.", "wireservice"); ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_connected): ?>
        <hr>

        <h2><?php esc_html_e("Publication", "wireservice"); ?></h2>
        <p class="description"><?php esc_html_e("Configure your site's publication record on AT Protocol. This represents your WordPress site in the ATmosphere.", "wireservice"); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>">
            <?php wp_nonce_field("wireservice_sync_publication", "wireservice_pub_nonce"); ?>
            <input type="hidden" name="action" value="wireservice_sync_publication">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wireservice_pub_url"><?php esc_html_e("Site URL", "wireservice"); ?></label>
                    </th>
                    <td>
                        <input type="url" name="wireservice_pub_url" id="wireservice_pub_url" class="regular-text" value="<?php echo esc_attr(home_url()); ?>">
                        <p class="description"><?php esc_html_e("The base URL of your publication.", "wireservice"); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wireservice_pub_name_source"><?php esc_html_e("Name", "wireservice"); ?></label>
                    </th>
                    <td>
                        <select name="wireservice_pub_name_source" id="wireservice_pub_name_source" class="regular-text">
                            <?php foreach ($name_sources as $key => $source): ?>
                                <option value="<?php echo esc_attr($key); ?>" data-value="<?php echo esc_attr($source["value"]); ?>" <?php selected($name_source, $key); ?>>
                                    <?php echo esc_html($source["label"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description" id="wireservice-pub-name-current-value">
                            <?php esc_html_e("Current value:", "wireservice"); ?>
                            <strong class="wireservice-preview-text"><?php echo esc_html(
                              $name_sources[$name_source]["value"] ?? $name_sources["wordpress_title"]["value"],
                            ); ?></strong>
                        </p>
                        <div id="wireservice-pub-custom-name-field" style="display:none; margin-top: 8px;">
                            <input type="text"
                                   name="wireservice_pub_custom_name"
                                   id="wireservice_pub_custom_name"
                                   class="regular-text"
                                   value="<?php echo esc_attr($custom_name); ?>"
                            />
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wireservice_pub_description_source"><?php esc_html_e("Description", "wireservice"); ?></label>
                    </th>
                    <td>
                        <select name="wireservice_pub_description_source" id="wireservice_pub_description_source" class="regular-text">
                            <?php foreach ($desc_sources as $key => $source): ?>
                                <option value="<?php echo esc_attr($key); ?>" data-value="<?php echo esc_attr($source["value"]); ?>" <?php selected($desc_source, $key); ?>>
                                    <?php echo esc_html($source["label"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description" id="wireservice-pub-desc-current-value">
                            <?php esc_html_e("Current value:", "wireservice"); ?>
                            <em class="wireservice-preview-text"><?php
                            $desc_value = $desc_sources[$desc_source]["value"] ?? $desc_sources["wordpress_tagline"]["value"];
                            echo esc_html(mb_substr($desc_value, 0, 100) . (mb_strlen($desc_value) > 100 ? "..." : ""));
                            ?></em>
                        </p>
                        <div id="wireservice-pub-custom-desc-field" style="display:none; margin-top: 8px;">
                            <textarea name="wireservice_pub_custom_description"
                                      id="wireservice_pub_custom_description"
                                      class="large-text"
                                      rows="3"
                            ><?php echo esc_textarea($custom_description); ?></textarea>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wireservice_pub_icon_source"><?php esc_html_e("Icon", "wireservice"); ?></label>
                    </th>
                    <td>
                        <select name="wireservice_pub_icon_source" id="wireservice_pub_icon_source" class="regular-text">
                            <?php foreach ($icon_sources as $key => $source): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($icon_source, $key); ?>>
                                    <?php echo esc_html($source["label"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e("Square image to identify your publication. Should be at least 256×256 and less than 1MB.", "wireservice"); ?></p>
                        <div id="wireservice-pub-icon-preview" style="margin-top: 8px;<?php echo empty($icon_preview_url) ? ' display:none;' : ''; ?>">
                            <?php if ($icon_preview_url): ?>
                                <img src="<?php echo esc_url($icon_preview_url); ?>" alt="" style="width: 64px; height: 64px; object-fit: cover; border-radius: 4px;">
                            <?php endif; ?>
                        </div>
                        <div id="wireservice-pub-custom-icon-field" style="display:none; margin-top: 8px;">
                            <input type="hidden" name="wireservice_pub_custom_icon_id" id="wireservice_pub_custom_icon_id" value="<?php echo esc_attr($custom_icon_id); ?>">
                            <button type="button" class="button" id="wireservice-pub-icon-upload"><?php esc_html_e("Select Image", "wireservice"); ?></button>
                            <button type="button" class="button" id="wireservice-pub-icon-remove" style="<?php echo empty($custom_icon_id) ? 'display:none;' : ''; ?>"><?php esc_html_e("Remove", "wireservice"); ?></button>
                            <div id="wireservice-pub-custom-icon-preview" style="margin-top: 8px;">
                                <?php if ($custom_icon_id): ?>
                                    <?php $custom_preview = wp_get_attachment_image_url($custom_icon_id, [64, 64]); ?>
                                    <?php if ($custom_preview): ?>
                                        <img src="<?php echo esc_url($custom_preview); ?>" alt="" style="width: 64px; height: 64px; object-fit: cover; border-radius: 4px;">
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row" colspan="2">
                        <h3 style="margin: 0;"><?php esc_html_e("Theme", "wireservice"); ?></h3>
                        <p class="description" style="font-weight: normal;"><?php esc_html_e("Set theme colors for your publication. All four colors are required for the theme to be included.", "wireservice"); ?></p>
                    </th>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wireservice_pub_theme_background"><?php esc_html_e("Background", "wireservice"); ?></label>
                    </th>
                    <td>
                        <input type="text" name="wireservice_pub_theme_background" id="wireservice_pub_theme_background" class="wireservice-color-picker" value="<?php echo esc_attr($theme_background); ?>" data-default-color="">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wireservice_pub_theme_foreground"><?php esc_html_e("Foreground", "wireservice"); ?></label>
                    </th>
                    <td>
                        <input type="text" name="wireservice_pub_theme_foreground" id="wireservice_pub_theme_foreground" class="wireservice-color-picker" value="<?php echo esc_attr($theme_foreground); ?>" data-default-color="">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wireservice_pub_theme_accent"><?php esc_html_e("Accent", "wireservice"); ?></label>
                    </th>
                    <td>
                        <input type="text" name="wireservice_pub_theme_accent" id="wireservice_pub_theme_accent" class="wireservice-color-picker" value="<?php echo esc_attr($theme_accent); ?>" data-default-color="">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wireservice_pub_theme_accent_foreground"><?php esc_html_e("Accent Foreground", "wireservice"); ?></label>
                    </th>
                    <td>
                        <input type="text" name="wireservice_pub_theme_accent_foreground" id="wireservice_pub_theme_accent_foreground" class="wireservice-color-picker" value="<?php echo esc_attr($theme_accent_foreground); ?>" data-default-color="">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e("Discovery", "wireservice"); ?></th>
                    <td>
                        <label for="wireservice_pub_show_in_discover">
                            <input type="checkbox"
                                   name="wireservice_pub_show_in_discover"
                                   id="wireservice_pub_show_in_discover"
                                   value="1"
                                   <?php checked($show_in_discover, "1"); ?> />
                            <?php esc_html_e("Show this publication in discovery feeds on AT Protocol apps", "wireservice"); ?>
                        </label>
                    </td>
                </tr>
                <?php if ($pub_uri): ?>
                <tr>
                    <th scope="row"><?php esc_html_e("AT-URI", "wireservice"); ?></th>
                    <td>
                        <code><?php echo esc_html($pub_uri); ?></code>
                        <p class="description"><?php esc_html_e("Your publication's identifier on AT Protocol.", "wireservice"); ?></p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <?php submit_button(
              $pub_uri
                ? __("Update Publication", "wireservice")
                : __("Create Publication", "wireservice"),
            ); ?>
        </form>

        <hr>

        <h2><?php esc_html_e("Document Settings", "wireservice"); ?></h2>
        <p class="description"><?php esc_html_e("Configure how document metadata is sourced when posts are synced to AT Protocol.", "wireservice"); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>">
            <?php wp_nonce_field("wireservice_save_doc_settings", "wireservice_doc_nonce"); ?>
            <input type="hidden" name="action" value="wireservice_save_doc_settings">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e("Enable by default", "wireservice"); ?></th>
                    <td>
                        <label for="wireservice_doc_enabled">
                            <input type="checkbox"
                                   name="wireservice_doc_enabled"
                                   id="wireservice_doc_enabled"
                                   value="1"
                                   <?php checked($doc_enabled, "1"); ?> />
                            <?php esc_html_e("Automatically publish new posts to AT Protocol", "wireservice"); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wireservice_doc_title_source"><?php esc_html_e("Document Title Source", "wireservice"); ?></label>
                    </th>
                    <td>
                        <select name="wireservice_doc_title_source" id="wireservice_doc_title_source">
                            <?php foreach ($doc_title_sources as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($doc_title_source, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e("Where to get the title for document records.", "wireservice"); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wireservice_doc_description_source"><?php esc_html_e("Document Description Source", "wireservice"); ?></label>
                    </th>
                    <td>
                        <select name="wireservice_doc_description_source" id="wireservice_doc_description_source">
                            <?php foreach ($doc_desc_sources as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($doc_desc_source, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e("Where to get the description for document records.", "wireservice"); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wireservice_doc_image_source"><?php esc_html_e("Cover Image Source", "wireservice"); ?></label>
                    </th>
                    <td>
                        <select name="wireservice_doc_image_source" id="wireservice_doc_image_source">
                            <?php foreach ($doc_image_sources as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($doc_image_source, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e("Where to get the cover image for document records. Images must be less than 1MB.", "wireservice"); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e("Full Content", "wireservice"); ?></th>
                    <td>
                        <label for="wireservice_doc_include_content">
                            <input type="checkbox"
                                   name="wireservice_doc_include_content"
                                   id="wireservice_doc_include_content"
                                   value="1"
                                   <?php checked($doc_include_content, "1"); ?> />
                            <?php esc_html_e("Include full post text content in document records", "wireservice"); ?>
                        </label>
                        <p class="description"><?php esc_html_e("When enabled, the plain text of each post will be synced in the textContent field.", "wireservice"); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__("Save Document Settings", "wireservice")); ?>
        </form>

        <?php if ($pub_uri): ?>
        <hr>

        <h2><?php esc_html_e("Backfill Documents", "wireservice"); ?></h2>
        <p class="description"><?php esc_html_e("Sync all existing published posts that haven't been published to AT Protocol yet.", "wireservice"); ?></p>

        <div id="wireservice-backfill">
            <p>
                <button type="button" class="button button-secondary" id="wireservice-backfill-start">
                    <?php esc_html_e("Backfill Posts", "wireservice"); ?>
                </button>
            </p>

            <div id="wireservice-backfill-progress" style="display: none;">
                <div class="wireservice-progress-bar">
                    <div class="wireservice-progress-bar-fill" style="width: 0%;"></div>
                </div>
                <p id="wireservice-backfill-status"></p>
            </div>

            <div id="wireservice-backfill-results" style="display: none;"></div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <hr>

        <details class="wireservice-advanced-settings">
            <summary><?php esc_html_e("Advanced Settings", "wireservice"); ?></summary>
            <form method="post" action="options.php">
                <?php settings_fields("wireservice"); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wireservice_oauth_url">
                                <?php esc_html_e("OAuth Service URL", "wireservice"); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" name="wireservice_oauth_url" id="wireservice_oauth_url" class="regular-text" value="<?php echo esc_attr(get_option("wireservice_oauth_url")); ?>" placeholder="https://example.com">
                            <p class="description">
                                <?php esc_html_e("The URL of the OAuth service for AT Protocol authentication.", "wireservice"); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </details>

        <hr>

        <h2><?php esc_html_e("Reset Plugin Data", "wireservice"); ?></h2>
        <p class="description"><?php esc_html_e("This will remove all plugin settings, stored connections, and document sync data from the database. This action cannot be undone.", "wireservice"); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>" onsubmit="return confirm('<?php echo esc_js(__("Are you sure you want to reset all Wireservice data? This cannot be undone.", "wireservice")); ?>');">
            <?php wp_nonce_field("wireservice_reset_data", "wireservice_reset_nonce"); ?>
            <input type="hidden" name="action" value="wireservice_reset_data">
            <button type="submit" class="button button-secondary" style="color: #dc3545;">
                <?php esc_html_e("Reset All Data", "wireservice"); ?>
            </button>
        </form>
    </div>
    <?php elseif ($active_tab === "records"): ?>
    <?php include WIRESERVICE_PLUGIN_DIR . "templates/records-page.php"; ?>
    <?php endif; ?>
</div>
<style>
    .wireservice-settings {
        max-width: 800px;
    }
    .wireservice-connection-status {
        padding: 15px;
        border-radius: 4px;
        margin: 15px 0;
    }
    .wireservice-connection-status.connected {
        background: #d4edda;
        border: 1px solid #c3e6cb;
    }
    .wireservice-connection-status.disconnected {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .wireservice-error {
        color: #dc3545;
        font-weight: 500;
    }
    .wireservice-profile {
        display: flex;
        gap: 15px;
        align-items: flex-start;
        margin-bottom: 15px;
    }
    .wireservice-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        object-fit: cover;
    }
    .wireservice-profile-info {
        flex: 1;
    }
    .wireservice-display-name {
        display: block;
        font-size: 16px;
        margin-bottom: 2px;
    }
    .wireservice-handle {
        color: #666;
        font-size: 14px;
    }
    .wireservice-bio {
        margin: 8px 0;
        font-size: 14px;
        color: #333;
    }
    .wireservice-stats {
        display: flex;
        gap: 15px;
        font-size: 13px;
        color: #666;
        margin: 8px 0 0;
    }
    .wireservice-advanced-settings summary {
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        color: #50575e;
        padding: 4px 0;
    }
    .wireservice-advanced-settings summary:hover {
        color: #1d2327;
    }
    .wireservice-progress-bar {
        width: 100%;
        height: 20px;
        background: #f0f0f1;
        border-radius: 3px;
        overflow: hidden;
        margin: 10px 0;
    }
    .wireservice-progress-bar-fill {
        height: 100%;
        background: #2271b1;
        transition: width 0.3s ease;
    }
    .wireservice-backfill-errors {
        margin-top: 10px;
    }
    .wireservice-backfill-errors summary {
        cursor: pointer;
        color: #d63638;
        font-weight: 500;
    }
    .wireservice-records {
        max-width: 800px;
    }
    .wireservice-record-table {
        border-collapse: collapse;
    }
    .wireservice-record-table th {
        width: 160px;
        text-align: left;
        padding: 8px 12px;
        vertical-align: top;
        font-weight: 600;
        white-space: nowrap;
    }
    .wireservice-record-table td {
        padding: 8px 12px;
        word-break: break-all;
    }
    .wireservice-record-table code {
        font-size: 12px;
        background: #f0f0f1;
        padding: 2px 6px;
        border-radius: 3px;
    }
    .wireservice-document-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        margin-bottom: 16px;
    }
    .wireservice-document-card-header {
        padding: 12px 16px;
        border-bottom: 1px solid #f0f0f1;
    }
    .wireservice-document-card-header h3 {
        margin: 0;
        font-size: 14px;
    }
    .wireservice-document-card-body {
        padding: 0;
    }
    .wireservice-document-card-body .wireservice-record-table {
        border: none;
    }
    .wireservice-color-swatch {
        display: inline-block;
        width: 16px;
        height: 16px;
        border-radius: 3px;
        border: 1px solid #ddd;
        vertical-align: middle;
        margin-right: 4px;
    }
</style>
