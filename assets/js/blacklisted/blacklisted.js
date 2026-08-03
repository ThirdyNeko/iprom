$(function () {
  // For $.ajax() failures — xhr is jQuery's jqXHR object
  function getAjaxErrorMessage(xhr) {
    if (xhr.responseJSON && xhr.responseJSON.message) {
      return xhr.responseJSON.message;
    }

    if (xhr.responseText) {
      try {
        const parsed = JSON.parse(xhr.responseText);
        if (parsed.message) return parsed.message;
      } catch (e) {
        const text = xhr.responseText.replace(/<[^>]*>/g, "").trim();
        if (text) return text.substring(0, 500);
      }
    }

    return `Something went wrong (HTTP ${xhr.status || "unknown"}).`;
  }

  function initBlacklistedTable(tableSelector, filterInputId, category) {
    const table = $(tableSelector).DataTable({
      processing: true,
      serverSide: true,
      pageLength: 25,
      responsive: true,
      dom: "lrtip",
      ordering: false,
      ajax: {
        url: "functions/get_blacklisted.php",
        type: "POST",
        data: function (d) {
          d.search.value = $("#" + filterInputId).val();
          d.category = category;
        },
        // DataTables' own default failure behavior is a generic "Ajax error"
        // popup with no detail. Override it so the real server message shows.
        error: function (xhr) {
          $(tableSelector + "_processing").hide();
          Swal.fire({
            icon: "error",
            title: "Failed to Load Records",
            text: getAjaxErrorMessage(xhr),
          });
        },
      },
      columns: [
        { data: "id", name: "id", visible: false, searchable: false },
        { data: "full_name", name: "full_name" },
        { data: "branch", name: "branch" },
        { data: "brand", name: "brand" },
        { data: "employment_status", name: "employment_status" },
      ],
      rowCallback: function (row, data) {
        $(row).attr("data-id", data.id);
        $(row).css("cursor", "pointer");
      },
      language: {
        emptyTable: "No blacklisted records found.",
      },
    });

    let searchTimeout;
    $("#" + filterInputId).on("input", function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => table.ajax.reload(), 400);
    });

    return table;
  }

  const promodiserTable = initBlacklistedTable(
    "#BlacklistedtablePromodiser",
    "filterNamePromodiser",
    "promodiser",
  );

  const directHireTable = initBlacklistedTable(
    "#BlacklistedtableDirectHire",
    "filterNameDirectHire",
    "direct_hire",
  );

  $("#syncBlacklistBtn").on("click", function () {
    Swal.fire({
      title: "Sync Blacklisted Records?",
      text: "This will import any employees marked BLACKLISTED / AWOL / TERMINATED that aren't already in this list.",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Sync",
    }).then((result) => {
      if (!result.isConfirmed) return;

      Swal.fire({
        title: "Syncing...",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });

      $.ajax({
        url: "functions/sync_blacklisted.php",
        type: "POST",
        dataType: "json",
      })
        .done(function (res) {
          if (res.success) {
            Swal.fire({
              icon: "success",
              title: "Sync Complete",
              text: `${res.insertedCount} new record(s) added.`,
            });
            promodiserTable.ajax.reload(null, false);
            directHireTable.ajax.reload(null, false);
          } else {
            Swal.fire("Error", res.message || "Sync failed.", "error");
          }
        })
        .fail(function (xhr) {
          Swal.fire("Error", getAjaxErrorMessage(xhr), "error");
        });
    });
  });
});
