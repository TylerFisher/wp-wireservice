<?php
/**
 * Document settings meta box template for Wireservice.
 *
 * @package Wireservice
 *
 * @var string $title_source
 * @var string $custom_title
 * @var array  $title_sources
 * @var string $desc_source
 * @var string $custom_description
 * @var array  $desc_sources
 * @var string $image_source
 * @var string $custom_image_id
 * @var array  $image_sources
 * @var string $include_content
 * @var string $global_label
 * @var string $at_uri
 */

defined('ABSPATH') || exit;
?>
<p>
  <label for="wireservice_title_source">
    <strong><?php esc_html_e("Title Source", "wireservice"); ?></strong>
  </label><br>
  <select name="wireservice_title_source" id="wireservice_title_source" style="width: 100%;">
    <?php foreach ($title_sources as $wireservice_key => $wireservice_label): ?>
      <option value="<?php echo esc_attr($wireservice_key); ?>" <?php selected($title_source, $wireservice_key); ?>>
        <?php echo esc_html($wireservice_label); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div id="wireservice-custom-title-field" style="display:none; margin-top: 8px;">
    <label for="wireservice_custom_title">
      <?php esc_html_e("Custom Title", "wireservice"); ?>
    </label><br>
    <input type="text"
           name="wireservice_custom_title"
           id="wireservice_custom_title"
           value="<?php echo esc_attr($custom_title); ?>"
           style="width: 100%;"
    />
  </div>
</p>
<p>
  <label for="wireservice_description_source">
    <strong><?php esc_html_e("Description Source", "wireservice"); ?></strong>
  </label><br>
  <select name="wireservice_description_source" id="wireservice_description_source" style="width: 100%;">
    <?php foreach ($desc_sources as $wireservice_key => $wireservice_label): ?>
      <option value="<?php echo esc_attr($wireservice_key); ?>" <?php selected($desc_source, $wireservice_key); ?>>
        <?php echo esc_html($wireservice_label); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div id="wireservice-custom-description-field" style="display:none; margin-top: 8px;">
    <label for="wireservice_custom_description">
      <?php esc_html_e("Custom Description", "wireservice"); ?>
    </label><br>
    <textarea name="wireservice_custom_description"
              id="wireservice_custom_description"
              style="width: 100%;"
              rows="3"
    ><?php echo esc_textarea($custom_description); ?></textarea>
  </div>
</p>
<p>
  <label for="wireservice_image_source">
    <strong><?php esc_html_e("Cover Image Source", "wireservice"); ?></strong>
  </label><br>
  <select name="wireservice_image_source" id="wireservice_image_source" style="width: 100%;">
    <?php foreach ($image_sources as $wireservice_key => $wireservice_label): ?>
      <option value="<?php echo esc_attr($wireservice_key); ?>" <?php selected($image_source, $wireservice_key); ?>>
        <?php echo esc_html($wireservice_label); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div id="wireservice-custom-image-field" style="display:none; margin-top: 8px;">
    <div id="wireservice-custom-image-preview">
      <?php if (!empty($custom_image_id)):
        $wireservice_thumb = wp_get_attachment_image_url($custom_image_id, "thumbnail");
      ?>
        <img src="<?php echo esc_url($wireservice_thumb); ?>"
             style="max-width:150px;height:auto;display:block;margin-bottom:8px;" />
      <?php endif; ?>
    </div>
    <input type="hidden"
           name="wireservice_custom_image_id"
           id="wireservice_custom_image_id"
           value="<?php echo esc_attr($custom_image_id); ?>" />
    <button type="button"
            class="button"
            id="wireservice-select-image">
      <?php esc_html_e("Select Image", "wireservice"); ?>
    </button>
    <button type="button"
            class="button"
            id="wireservice-remove-image"
            style="<?php echo esc_attr(empty($custom_image_id) ? 'display:none;' : ''); ?>">
      <?php esc_html_e("Remove Image", "wireservice"); ?>
    </button>
    <p class="description"><?php esc_html_e("Images must be less than 1MB.", "wireservice"); ?></p>
  </div>
</p>
<p>
  <label for="wireservice_include_content">
    <strong><?php esc_html_e("Include Full Content", "wireservice"); ?></strong>
  </label><br>
  <select name="wireservice_include_content" id="wireservice_include_content" style="width: 100%;">
    <option value="" <?php selected($include_content, ""); ?>>
      <?php echo esc_html(sprintf(
        /* translators: %s: On or Off */
        __("Use global setting (%s)", "wireservice"),
        $global_label,
      )); ?>
    </option>
    <option value="1" <?php selected($include_content, "1"); ?>>
      <?php esc_html_e("Include full content", "wireservice"); ?>
    </option>
    <option value="0" <?php selected($include_content, "0"); ?>>
      <?php esc_html_e("Don't include full content", "wireservice"); ?>
    </option>
  </select>
</p>
<?php if ($at_uri): ?>
<p>
  <strong><?php esc_html_e("AT-URI", "wireservice"); ?></strong><br>
  <code style="font-size: 11px; word-break: break-all;"><?php echo esc_html($at_uri); ?></code>
</p>
<?php endif; ?>
