(function () {
  "use strict";

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str || "";
    return div.innerHTML;
  }

  function toggleCustomField(selectId, fieldId, currentValueId) {
    var select = document.getElementById(selectId);
    var field = document.getElementById(fieldId);
    var currentValue = document.getElementById(currentValueId);
    var previewText = currentValue
      ? currentValue.querySelector(".wireservice-preview-text")
      : null;

    function update() {
      var selected = select.options[select.selectedIndex];
      var preview = selected.getAttribute("data-value") || "";

      if (select.value === "custom") {
        field.style.display = "";
        if (currentValue) currentValue.style.display = "none";
      } else {
        field.style.display = "none";
        if (currentValue) currentValue.style.display = "";
        if (previewText) {
          var truncated =
            preview.length > 100
              ? preview.substring(0, 100) + "..."
              : preview;
          previewText.textContent = truncated || "\u2014";
        }
      }
    }

    select.addEventListener("change", update);
    update();
  }

  function initIconField() {
    var select = document.getElementById("wireservice_pub_icon_source");
    var customField = document.getElementById(
      "wireservice-pub-custom-icon-field"
    );
    var preview = document.getElementById("wireservice-pub-icon-preview");
    var uploadBtn = document.getElementById("wireservice-pub-icon-upload");
    var removeBtn = document.getElementById("wireservice-pub-icon-remove");
    var hiddenInput = document.getElementById("wireservice_pub_custom_icon_id");
    var customPreview = document.getElementById(
      "wireservice-pub-custom-icon-preview"
    );
    var frame;

    function updateVisibility() {
      var val = select.value;
      if (val === "custom") {
        customField.style.display = "";
        preview.style.display = "none";
      } else if (val === "none") {
        customField.style.display = "none";
        preview.style.display = "none";
      } else {
        customField.style.display = "none";
        preview.style.display = "";
      }
    }

    select.addEventListener("change", updateVisibility);
    updateVisibility();

    uploadBtn.addEventListener("click", function (e) {
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
        hiddenInput.value = attachment.id;
        var thumbUrl =
          attachment.sizes && attachment.sizes.thumbnail
            ? attachment.sizes.thumbnail.url
            : attachment.url;
        customPreview.innerHTML = "";
        var img = document.createElement("img");
        img.src = thumbUrl;
        img.alt = "";
        img.style.cssText = "width: 64px; height: 64px; object-fit: cover; border-radius: 4px;";
        customPreview.appendChild(img);
        removeBtn.style.display = "";
      });

      frame.open();
    });

    removeBtn.addEventListener("click", function (e) {
      e.preventDefault();
      hiddenInput.value = "";
      customPreview.innerHTML = "";
      removeBtn.style.display = "none";
    });
  }

  function ajaxPost(url, data) {
    var formData = new FormData();
    for (var key in data) {
      if (Array.isArray(data[key])) {
        for (var i = 0; i < data[key].length; i++) {
          formData.append(key + "[]", data[key][i]);
        }
      } else {
        formData.append(key, data[key]);
      }
    }
    return fetch(url, { method: "POST", body: formData }).then(function (res) {
      return res.json();
    });
  }

  function initBackfill() {
    var button = document.getElementById("wireservice-backfill-start");
    var progress = document.getElementById("wireservice-backfill-progress");
    var fill = progress
      ? progress.querySelector(".wireservice-progress-bar-fill")
      : null;
    var status = document.getElementById("wireservice-backfill-status");
    var results = document.getElementById("wireservice-backfill-results");
    var batchSize = 5;

    if (!button) {
      return;
    }

    button.addEventListener("click", function () {
      button.disabled = true;
      progress.style.display = "";
      results.style.display = "none";
      results.innerHTML = "";
      fill.style.width = "0%";
      status.textContent = "Counting posts...";

      ajaxPost(wireserviceBackfill.ajaxUrl, {
        action: "wireservice_backfill_count",
        nonce: wireserviceBackfill.nonce,
      })
        .then(function (response) {
          if (!response.success) {
            status.textContent = "Failed to count posts.";
            button.disabled = false;
            return;
          }

          var postIds = response.data.post_ids;
          var total = postIds.length;

          if (total === 0) {
            status.textContent = "All posts are already synced.";
            progress.style.display = "none";
            button.disabled = false;
            return;
          }

          var processed = 0;
          var succeeded = 0;
          var errors = [];

          var batches = [];
          for (var i = 0; i < postIds.length; i += batchSize) {
            batches.push(postIds.slice(i, i + batchSize));
          }

          function processBatch(index) {
            if (index >= batches.length) {
              fill.style.width = "100%";

              var summary = succeeded + " of " + total + " posts synced.";
              status.textContent = summary;

              if (errors.length > 0) {
                var html =
                  '<details class="wireservice-backfill-errors"><summary>' +
                  errors.length +
                  " failed</summary><ul>";
                for (var e = 0; e < errors.length; e++) {
                  html +=
                    "<li><strong>" +
                    escapeHtml(errors[e].title) +
                    "</strong>: " +
                    escapeHtml(errors[e].error) +
                    "</li>";
                }
                html += "</ul></details>";
                results.innerHTML = html;
                results.style.display = "";
              }

              button.disabled = false;
              return;
            }

            status.textContent =
              "Syncing posts... (" + processed + " of " + total + ")";

            ajaxPost(wireserviceBackfill.ajaxUrl, {
              action: "wireservice_backfill_batch",
              nonce: wireserviceBackfill.nonce,
              post_ids: batches[index],
            })
              .then(function (response) {
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
                  processed += batches[index].length;
                  for (var f = 0; f < batches[index].length; f++) {
                    errors.push({
                      title: "Post #" + batches[index][f],
                      error: "Batch request failed.",
                    });
                  }
                }

                fill.style.width =
                  Math.round((processed / total) * 100) + "%";
                processBatch(index + 1);
              })
              .catch(function () {
                processed += batches[index].length;
                for (var f = 0; f < batches[index].length; f++) {
                  errors.push({
                    title: "Post #" + batches[index][f],
                    error: "Request failed.",
                  });
                }

                fill.style.width =
                  Math.round((processed / total) * 100) + "%";
                processBatch(index + 1);
              });
          }

          processBatch(0);
        })
        .catch(function () {
          status.textContent = "Failed to start backfill.";
          button.disabled = false;
        });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
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

    initIconField();

    jQuery(".wireservice-color-picker").wpColorPicker();

    initBackfill();

    var resetForm = document.getElementById("wireservice-reset-form");
    if (resetForm) {
      resetForm.addEventListener("submit", function (e) {
        if (!confirm(wireserviceBackfill.resetConfirm)) {
          e.preventDefault();
        }
      });
    }
  });
})();
