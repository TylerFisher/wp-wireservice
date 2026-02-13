(function ($) {
  "use strict";

  function formatDate(isoString) {
    if (!isoString) return "\u2014";
    var d = new Date(isoString);
    return d.toLocaleDateString(undefined, {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  function esc(str) {
    return $("<span>").text(str || "").html();
  }

  function fieldRow(label, value) {
    return "<tr><th>" + esc(label) + "</th><td>" + value + "</td></tr>";
  }

  function renderPublication(record) {
    var val = record.value;
    var html = '<table class="widefat wireservice-record-table">';
    html += "<tbody>";
    html += fieldRow("AT-URI", "<code>" + esc(record.uri) + "</code>");
    html += fieldRow("CID", "<code>" + esc(record.cid) + "</code>");
    html += fieldRow("Name", esc(val.name));
    html += fieldRow(
      "URL",
      '<a href="' +
        esc(val.url) +
        '" target="_blank">' +
        esc(val.url) +
        "</a>"
    );

    if (val.description) {
      html += fieldRow("Description", esc(val.description));
    }

    if (val.icon) {
      html += fieldRow(
        "Icon",
        "<code>blob</code> (" +
          esc(val.icon.mimeType || "unknown type") +
          ")"
      );
    }

    if (val.basicTheme) {
      var theme = val.basicTheme;
      var colors = ["background", "foreground", "accent", "accentForeground"];
      for (var i = 0; i < colors.length; i++) {
        var c = theme[colors[i]];
        if (c) {
          var rgb = "rgb(" + c.r + ", " + c.g + ", " + c.b + ")";
          html += fieldRow(
            "Theme: " + colors[i],
            '<span class="wireservice-color-swatch" style="background:' +
              rgb +
              ';"></span> ' +
              rgb
          );
        }
      }
    }

    if (val.preferences) {
      html += fieldRow(
        "Show in Discover",
        val.preferences.showInDiscover ? "Yes" : "No"
      );
    }

    html += "</tbody></table>";
    return html;
  }

  function renderDocumentCard(record) {
    var val = record.value;
    var html = '<div class="wireservice-document-card">';
    html += '<div class="wireservice-document-card-header">';
    html += "<h3>" + esc(val.title) + "</h3>";
    html += "</div>";
    html += '<div class="wireservice-document-card-body">';
    html += '<table class="widefat wireservice-record-table">';
    html += "<tbody>";
    html += fieldRow("AT-URI", "<code>" + esc(record.uri) + "</code>");

    if (val.path) {
      html += fieldRow("Path", esc(val.path));
    }

    html += fieldRow("Published", formatDate(val.publishedAt));

    if (val.updatedAt) {
      html += fieldRow("Updated", formatDate(val.updatedAt));
    }

    if (val.description) {
      var desc =
        val.description.length > 200
          ? val.description.substring(0, 200) + "..."
          : val.description;
      html += fieldRow("Description", esc(desc));
    }

    if (val.tags && val.tags.length > 0) {
      var tags = val.tags
        .map(function (t) {
          return esc(t);
        })
        .join(", ");
      html += fieldRow("Tags", tags);
    }

    if (val.coverImage) {
      html += fieldRow(
        "Cover Image",
        "<code>blob</code> (" +
          esc(val.coverImage.mimeType || "unknown type") +
          ")"
      );
    }

    if (val.textContent) {
      html += fieldRow(
        "Text Content",
        esc(val.textContent.substring(0, 100)) +
          (val.textContent.length > 100 ? "..." : "")
      );
    }

    html += "</tbody></table>";
    html += "</div></div>";
    return html;
  }

  function loadPublication() {
    var $loading = $("#wireservice-publication-loading");
    var $error = $("#wireservice-publication-error");
    var $data = $("#wireservice-publication-data");

    $.post(wireserviceRecords.ajaxUrl, {
      action: "wireservice_get_publication_record",
      nonce: wireserviceRecords.nonce,
    })
      .done(function (response) {
        $loading.hide();
        if (!response.success) {
          $error
            .html(
              "<p class='wireservice-error'>" + esc(response.data) + "</p>"
            )
            .show();
          return;
        }
        $data.html(renderPublication(response.data)).show();
      })
      .fail(function () {
        $loading.hide();
        $error
          .html(
            "<p class='wireservice-error'>Failed to fetch publication record.</p>"
          )
          .show();
      });
  }

  var documentCursor = null;
  var totalLoaded = 0;

  function loadDocuments(cursor) {
    var $loading = $("#wireservice-documents-loading");
    var $error = $("#wireservice-documents-error");
    var $list = $("#wireservice-documents-list");
    var $pagination = $("#wireservice-documents-pagination");
    var $loadMore = $("#wireservice-documents-load-more");

    if (!cursor) {
      $loading.show();
    }
    $loadMore.prop("disabled", true);

    var postData = {
      action: "wireservice_list_document_records",
      nonce: wireserviceRecords.nonce,
    };

    if (cursor) {
      postData.cursor = cursor;
    }

    $.post(wireserviceRecords.ajaxUrl, postData)
      .done(function (response) {
        $loading.hide();
        if (!response.success) {
          $error
            .html(
              "<p class='wireservice-error'>" + esc(response.data) + "</p>"
            )
            .show();
          return;
        }

        var records = response.data.records || [];
        documentCursor = response.data.cursor || null;
        totalLoaded += records.length;

        if (records.length === 0 && totalLoaded === 0) {
          $list.html("<p>No document records found on this PDS.</p>").show();
          return;
        }

        var html = "";
        for (var i = 0; i < records.length; i++) {
          html += renderDocumentCard(records[i]);
        }

        $list.append(html).show();

        var $count = $("#wireservice-documents-count");
        if ($count.length === 0) {
          $list.before(
            '<p id="wireservice-documents-count">' +
              totalLoaded +
              " records loaded</p>"
          );
        } else {
          $count.text(totalLoaded + " records loaded");
        }

        if (documentCursor) {
          $pagination.show();
          $loadMore.prop("disabled", false);
        } else {
          $pagination.hide();
        }
      })
      .fail(function () {
        $loading.hide();
        $error
          .html(
            "<p class='wireservice-error'>Failed to fetch document records.</p>"
          )
          .show();
      });
  }

  $(document).ready(function () {
    if ($("#wireservice-records-publication").length === 0) {
      return;
    }

    loadPublication();
    loadDocuments(null);

    $("#wireservice-documents-load-more").on("click", function () {
      if (documentCursor) {
        loadDocuments(documentCursor);
      }
    });
  });
})(jQuery);
