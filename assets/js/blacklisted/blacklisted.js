$(function () {
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

  const CATEGORY_LABELS = {
    promodiser: { text: "Promodiser", cls: "bg-primary" },
    direct_hire: { text: "Direct Hire", cls: "bg-secondary" },
  };

  const table = $("#Blacklistedtable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    responsive: true,
    autoWidth: false,
    dom: "lrtip",
    ordering: false,
    ajax: {
      url: "functions/get_blacklisted.php",
      type: "POST",
      data: function (d) {
        d.search.value = $("#filterName").val();
        d.category = $("#filterCategory").val(); // "all" | "promodiser" | "direct_hire"
      },
      error: function (xhr) {
        $("#Blacklistedtable_processing").hide();
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
      {
        data: "category",
        name: "category",
        defaultContent: "",
        render: function (val) {
          const c = CATEGORY_LABELS[val];
          return c ? `<span class="badge ${c.cls}">${c.text}</span>` : "";
        },
      },
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

  // Debounced search
  let searchDebounce;
  $("#filterName").on("input", function () {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => table.ajax.reload(), 400);
  });

  $("#filterCategory").on("change", () => table.ajax.reload());

  // Sync from Employees
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
            table.ajax.reload(null, false);
          } else {
            Swal.fire("Error", res.message || "Sync failed.", "error");
          }
        })
        .fail(function (xhr) {
          Swal.fire("Error", getAjaxErrorMessage(xhr), "error");
        });
    });
  });

  // Let the add/view scripts refresh the table
  window.blacklistedTable = table;
});
