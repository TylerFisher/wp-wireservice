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
  });
})(jQuery);
