<?php
/**
 * Records browser template for Wireservice.
 *
 * @package Wireservice
 */

defined('ABSPATH') || exit;
?>

<div class="wireservice-records">
    <div id="wireservice-records-publication">
        <h2><?php esc_html_e("Publication Record", "wireservice"); ?></h2>
        <p class="description">
            <?php esc_html_e(
              "The site.standard.publication record stored on your PDS.",
              "wireservice",
            ); ?>
        </p>
        <div id="wireservice-publication-loading">
            <p><?php esc_html_e("Loading publication record...", "wireservice"); ?></p>
        </div>
        <div id="wireservice-publication-error" style="display: none;"></div>
        <div id="wireservice-publication-data" style="display: none;"></div>
    </div>

    <hr>

    <div id="wireservice-records-documents">
        <h2><?php esc_html_e("Document Records", "wireservice"); ?></h2>
        <p class="description">
            <?php esc_html_e(
              "All site.standard.document records stored on your PDS.",
              "wireservice",
            ); ?>
        </p>
        <div id="wireservice-documents-loading">
            <p><?php esc_html_e("Loading document records...", "wireservice"); ?></p>
        </div>
        <div id="wireservice-documents-error" style="display: none;"></div>
        <div id="wireservice-documents-list" style="display: none;"></div>
        <div id="wireservice-documents-pagination" style="display: none;">
            <button type="button" class="button" id="wireservice-documents-load-more">
                <?php esc_html_e("Load More", "wireservice"); ?>
            </button>
        </div>
    </div>
</div>
