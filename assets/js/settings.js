(function ($) {
  "use strict";

  function toggleCustomField(selectId, fieldId, currentValueId) {
    var $select = $("#" + selectId);
    var $field = $("#" + fieldId);
    var $currentValue = $("#" + currentValueId);
    var $previewText = $currentValue.find(".wireservice-preview-text");

    function update() {
      var selected = $select.find("option:selected");
      var preview = selected.attr("data-value") || "";

      if ($select.val() === "custom") {
        $field.show();
        $currentValue.hide();
      } else {
        $field.hide();
        $currentValue.show();
        if ($previewText.length) {
          var truncated =
            preview.length > 100
              ? preview.substring(0, 100) + "..."
              : preview;
          $previewText.text(truncated || "—");
        }
      }
    }

    $select.on("change", update);
    update();
  }

  function initIconField() {
    var $select = $("#wireservice_pub_icon_source");
    var $customField = $("#wireservice-pub-custom-icon-field");
    var $preview = $("#wireservice-pub-icon-preview");
    var $uploadBtn = $("#wireservice-pub-icon-upload");
    var $removeBtn = $("#wireservice-pub-icon-remove");
    var $hiddenInput = $("#wireservice_pub_custom_icon_id");
    var $customPreview = $("#wireservice-pub-custom-icon-preview");
    var frame;

    function updateVisibility() {
      var val = $select.val();
      if (val === "custom") {
        $customField.show();
        $preview.hide();
      } else if (val === "none") {
        $customField.hide();
        $preview.hide();
      } else {
        $customField.hide();
        $preview.show();
      }
    }

    $select.on("change", updateVisibility);
    updateVisibility();

    $uploadBtn.on("click", function (e) {
      e.preventDefault();

      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: "Select Icon Image",
        button: { text: "Use as Icon" },
        multiple: false,
        library: { type: "image" },
      });

      frame.on("select", function () {
        var attachment = frame.state().get("selection").first().toJSON();
        $hiddenInput.val(attachment.id);
        var thumbUrl =
          attachment.sizes && attachment.sizes.thumbnail
            ? attachment.sizes.thumbnail.url
            : attachment.url;
        $customPreview.html(
          '<img src="' +
            thumbUrl +
            '" alt="" style="width: 64px; height: 64px; object-fit: cover; border-radius: 4px;">'
        );
        $removeBtn.show();
      });

      frame.open();
    });

    $removeBtn.on("click", function (e) {
      e.preventDefault();
      $hiddenInput.val("");
      $customPreview.html("");
      $removeBtn.hide();
    });
  }

  function initBackfill() {
    var $button = $("#wireservice-backfill-start");
    var $progress = $("#wireservice-backfill-progress");
    var $fill = $progress.find(".wireservice-progress-bar-fill");
    var $status = $("#wireservice-backfill-status");
    var $results = $("#wireservice-backfill-results");
    var batchSize = 5;

    if (!$button.length) {
      return;
    }

    $button.on("click", function () {
      $button.prop("disabled", true);
      $progress.show();
      $results.hide().empty();
      $fill.css("width", "0%");
      $status.text("Counting posts...");

      $.post(wireserviceBackfill.ajaxUrl, {
        action: "wireservice_backfill_count",
        nonce: wireserviceBackfill.nonce,
      })
        .done(function (response) {
          if (!response.success) {
            $status.text("Failed to count posts.");
            $button.prop("disabled", false);
            return;
          }

          var postIds = response.data.post_ids;
          var total = postIds.length;

          if (total === 0) {
            $status.text("All posts are already synced.");
            $progress.hide();
            $button.prop("disabled", false);
            return;
          }

          var processed = 0;
          var succeeded = 0;
          var errors = [];

          // Chunk post IDs into batches.
          var batches = [];
          for (var i = 0; i < postIds.length; i += batchSize) {
            batches.push(postIds.slice(i, i + batchSize));
          }

          function processBatch(index) {
            if (index >= batches.length) {
              // Done.
              var pct = "100%";
              $fill.css("width", pct);

              var summary = succeeded + " of " + total + " posts synced.";
              $status.text(summary);

              if (errors.length > 0) {
                var html =
                  '<details class="wireservice-backfill-errors"><summary>' +
                  errors.length +
                  " failed</summary><ul>";
                for (var e = 0; e < errors.length; e++) {
                  html +=
                    "<li><strong>" +
                    $("<span>").text(errors[e].title).html() +
                    "</strong>: " +
                    $("<span>").text(errors[e].error).html() +
                    "</li>";
                }
                html += "</ul></details>";
                $results.html(html).show();
              }

              $button.prop("disabled", false);
              return;
            }

            $status.text(
              "Syncing posts... (" + processed + " of " + total + ")"
            );

            $.post(wireserviceBackfill.ajaxUrl, {
              action: "wireservice_backfill_batch",
              nonce: wireserviceBackfill.nonce,
              post_ids: batches[index],
            })
              .done(function (response) {
                if (response.success && response.data.results) {
                  for (var r = 0; r < response.data.results.length; r++) {
                    var result = response.data.results[r];
                    processed++;
                    if (result.success) {
                      succeeded++;
                    } else {
                      errors.push(result);
                    }
                  }
                } else {
                  // Count the entire batch as failed.
                  processed += batches[index].length;
                  for (var f = 0; f < batches[index].length; f++) {
                    errors.push({
                      title: "Post #" + batches[index][f],
                      error: "Batch request failed.",
                    });
                  }
                }

                var pct = Math.round((processed / total) * 100) + "%";
                $fill.css("width", pct);

                processBatch(index + 1);
              })
              .fail(function () {
                processed += batches[index].length;
                for (var f = 0; f < batches[index].length; f++) {
                  errors.push({
                    title: "Post #" + batches[index][f],
                    error: "Request failed.",
                  });
                }

                var pct = Math.round((processed / total) * 100) + "%";
                $fill.css("width", pct);

                processBatch(index + 1);
              });
          }

          processBatch(0);
        })
        .fail(function () {
          $status.text("Failed to start backfill.");
          $button.prop("disabled", false);
        });
    });
  }

  $(document).ready(function () {
    toggleCustomField(
      "wireservice_pub_name_source",
      "wireservice-pub-custom-name-field",
      "wireservice-pub-name-current-value"
    );
    toggleCustomField(
      "wireservice_pub_description_source",
      "wireservice-pub-custom-desc-field",
      "wireservice-pub-desc-current-value"
    );

    // Initialize icon source toggle and media picker.
    initIconField();

    // Initialize color pickers.
    $(".wireservice-color-picker").wpColorPicker();

    // Initialize backfill.
    initBackfill();
  });
})(jQuery);
