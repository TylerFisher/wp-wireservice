(function () {
  "use strict";

  function toggleCustomField(selectId, fieldId) {
    var select = document.getElementById(selectId);
    var field = document.getElementById(fieldId);

    function update() {
      if (select.value === "custom") {
        field.style.display = "";
      } else {
        field.style.display = "none";
      }
    }

    select.addEventListener("change", update);
    update();
  }

  document.addEventListener("DOMContentLoaded", function () {
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

    document
      .getElementById("wireservice-select-image")
      .addEventListener("click", function (e) {
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
          document.getElementById("wireservice_custom_image_id").value =
            attachment.id;
          var thumbUrl =
            attachment.sizes && attachment.sizes.thumbnail
              ? attachment.sizes.thumbnail.url
              : attachment.url;
          var previewEl = document.getElementById("wireservice-custom-image-preview");
          previewEl.innerHTML = "";
          var img = document.createElement("img");
          img.src = thumbUrl;
          img.style.cssText = "max-width:150px;height:auto;display:block;margin-bottom:8px;";
          previewEl.appendChild(img);
          document.getElementById("wireservice-remove-image").style.display =
            "";
        });

        frame.open();
      });

    document
      .getElementById("wireservice-remove-image")
      .addEventListener("click", function (e) {
        e.preventDefault();
        document.getElementById("wireservice_custom_image_id").value = "";
        document.getElementById("wireservice-custom-image-preview").innerHTML =
          "";
        this.style.display = "none";
      });
  });
})();
