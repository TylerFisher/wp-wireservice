(function () {
  "use strict";

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str || "";
    return div.innerHTML;
  }

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

  function fieldRow(label, value) {
    return "<tr><th>" + escapeHtml(label) + "</th><td>" + value + "</td></tr>";
  }

  function renderPublication(record) {
    var val = record.value;
    var html = '<table class="widefat wireservice-record-table">';
    html += "<tbody>";
    html += fieldRow("AT-URI", "<code>" + escapeHtml(record.uri) + "</code>");
    html += fieldRow("CID", "<code>" + escapeHtml(record.cid) + "</code>");
    html += fieldRow("Name", escapeHtml(val.name));
    html += fieldRow(
      "URL",
      '<a href="' +
        escapeHtml(val.url) +
        '" target="_blank">' +
        escapeHtml(val.url) +
        "</a>"
    );

    if (val.description) {
      html += fieldRow("Description", escapeHtml(val.description));
    }

    if (val.icon) {
      html += fieldRow(
        "Icon",
        "<code>blob</code> (" +
          escapeHtml(val.icon.mimeType || "unknown type") +
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
    html += "<h3>" + escapeHtml(val.title) + "</h3>";
    html += "</div>";
    html += '<div class="wireservice-document-card-body">';
    html += '<table class="widefat wireservice-record-table">';
    html += "<tbody>";
    html += fieldRow("AT-URI", "<code>" + escapeHtml(record.uri) + "</code>");

    if (val.path) {
      html += fieldRow("Path", escapeHtml(val.path));
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
      html += fieldRow("Description", escapeHtml(desc));
    }

    if (val.tags && val.tags.length > 0) {
      var tags = val.tags
        .map(function (t) {
          return escapeHtml(t);
        })
        .join(", ");
      html += fieldRow("Tags", tags);
    }

    if (val.coverImage) {
      html += fieldRow(
        "Cover Image",
        "<code>blob</code> (" +
          escapeHtml(val.coverImage.mimeType || "unknown type") +
          ")"
      );
    }

    if (val.textContent) {
      html += fieldRow(
        "Text Content",
        escapeHtml(val.textContent.substring(0, 100)) +
          (val.textContent.length > 100 ? "..." : "")
      );
    }

    html += "</tbody></table>";
    html += "</div></div>";
    return html;
  }

  function ajaxPost(url, data) {
    var formData = new FormData();
    for (var key in data) {
      formData.append(key, data[key]);
    }
    return fetch(url, { method: "POST", body: formData }).then(function (res) {
      return res.json();
    });
  }

  function loadPublication() {
    var loading = document.getElementById("wireservice-publication-loading");
    var error = document.getElementById("wireservice-publication-error");
    var data = document.getElementById("wireservice-publication-data");

    ajaxPost(wireserviceRecords.ajaxUrl, {
      action: "wireservice_get_publication_record",
      nonce: wireserviceRecords.nonce,
    })
      .then(function (response) {
        loading.style.display = "none";
        if (!response.success) {
          error.innerHTML =
            "<p class='wireservice-error'>" +
            escapeHtml(response.data) +
            "</p>";
          error.style.display = "";
          return;
        }
        data.innerHTML = renderPublication(response.data);
        data.style.display = "";
      })
      .catch(function () {
        loading.style.display = "none";
        error.innerHTML =
          "<p class='wireservice-error'>Failed to fetch publication record.</p>";
        error.style.display = "";
      });
  }

  var documentCursor = null;
  var totalLoaded = 0;

  function loadDocuments(cursor) {
    var loading = document.getElementById("wireservice-documents-loading");
    var error = document.getElementById("wireservice-documents-error");
    var list = document.getElementById("wireservice-documents-list");
    var pagination = document.getElementById(
      "wireservice-documents-pagination"
    );
    var loadMore = document.getElementById("wireservice-documents-load-more");

    if (!cursor) {
      loading.style.display = "";
    }
    loadMore.disabled = true;

    var postData = {
      action: "wireservice_list_document_records",
      nonce: wireserviceRecords.nonce,
    };

    if (cursor) {
      postData.cursor = cursor;
    }

    ajaxPost(wireserviceRecords.ajaxUrl, postData)
      .then(function (response) {
        loading.style.display = "none";
        if (!response.success) {
          error.innerHTML =
            "<p class='wireservice-error'>" +
            escapeHtml(response.data) +
            "</p>";
          error.style.display = "";
          return;
        }

        var records = response.data.records || [];
        documentCursor = response.data.cursor || null;
        totalLoaded += records.length;

        if (records.length === 0 && totalLoaded === 0) {
          list.innerHTML = "<p>No document records found on this PDS.</p>";
          list.style.display = "";
          return;
        }

        var html = "";
        for (var i = 0; i < records.length; i++) {
          html += renderDocumentCard(records[i]);
        }

        list.insertAdjacentHTML("beforeend", html);
        list.style.display = "";

        var count = document.getElementById("wireservice-documents-count");
        if (!count) {
          list.insertAdjacentHTML(
            "beforebegin",
            '<p id="wireservice-documents-count">' +
              totalLoaded +
              " records loaded</p>"
          );
        } else {
          count.textContent = totalLoaded + " records loaded";
        }

        if (documentCursor) {
          pagination.style.display = "";
          loadMore.disabled = false;
        } else {
          pagination.style.display = "none";
        }
      })
      .catch(function () {
        loading.style.display = "none";
        error.innerHTML =
          "<p class='wireservice-error'>Failed to fetch document records.</p>";
        error.style.display = "";
      });
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (!document.getElementById("wireservice-records-publication")) {
      return;
    }

    loadPublication();
    loadDocuments(null);

    document
      .getElementById("wireservice-documents-load-more")
      .addEventListener("click", function () {
        if (documentCursor) {
          loadDocuments(documentCursor);
        }
      });
  });
})();
