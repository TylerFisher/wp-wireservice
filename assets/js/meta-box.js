(function ($) {
  "use strict";

  function toggleCustomField(selectId, fieldId) {
    var $select = $("#" + selectId);
    var $field = $("#" + fieldId);

    function update() {
      if ($select.val() === "custom") {
        $field.show();
      } else {
        $field.hide();
      }
    }

    $select.on("change", update);
    update();
  }

  $(document).ready(function () {
    toggleCustomField(
      "wireservice_title_source",
      "wireservice-custom-title-field"
    );
    toggleCustomField(
      "wireservice_description_source",
      "wireservice-custom-description-field"
    );
    toggleCustomField(
      "wireservice_image_source",
      "wireservice-custom-image-field"
    );

    var frame;

    $("#wireservice-select-image").on("click", function (e) {
      e.preventDefault();

      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: wireserviceMetaBox.selectImageTitle,
        button: { text: wireserviceMetaBox.useImageButton },
        multiple: false,
        library: { type: "image" },
      });

      frame.on("select", function () {
        var attachment = frame.state().get("selection").first().toJSON();
        $("#wireservice_custom_image_id").val(attachment.id);
        var thumbUrl =
          attachment.sizes && attachment.sizes.thumbnail
            ? attachment.sizes.thumbnail.url
            : attachment.url;
        $("#wireservice-custom-image-preview").html(
          '<img src="' +
            thumbUrl +
            '" style="max-width:150px;height:auto;display:block;margin-bottom:8px;" />'
        );
        $("#wireservice-remove-image").show();
      });

      frame.open();
    });

    $("#wireservice-remove-image").on("click", function (e) {
      e.preventDefault();
      $("#wireservice_custom_image_id").val("");
      $("#wireservice-custom-image-preview").html("");
      $(this).hide();
    });
  });
})(jQuery);
