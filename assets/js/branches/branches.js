var table;

$(document).ready(function () {
  table = $("#Branchtable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    ordering: false,

    ajax: {
      url: "functions/fetch_branches.php",
      type: "POST",
    },

    columns: [
      { data: "branch" },
      { data: "corpo" },
      { data: "region" },
      { data: "area" },
      { data: "director" },

      {
        data: "status",
        render: function (data, type, row) {
          const isActive = String(data).toLowerCase() === "active";
          const checked = isActive ? "checked" : "";

          const label = isActive ? "Active" : "Inactive";
          const badgeClass = isActive ? "bg-success" : "bg-secondary";

          return `
            <div class="d-flex align-items-center justify-content-center gap-2">

              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input branch-status-switch"
                  type="checkbox"
                  role="switch"
                  data-code="${row.branch_code}"
                  ${checked}
                >
              </div>

              <span class="badge ${badgeClass}">
                ${label}
              </span>

            </div>
          `;
        },
      },
    ],
  });

  // =========================
  // SYNC BRANCHES
  // =========================
  $(document).on("click", "#syncBranchesBtn", function () {
    const btn = $(this);

    Swal.fire({
      title: "Sync Branches?",
      text: "This will sync branches from the source system.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, Sync",
      cancelButtonText: "Cancel",
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
    }).then((result) => {
      if (!result.isConfirmed) return;

      btn.prop("disabled", true);

      Swal.fire({
        title: "Syncing Branches...",
        html: "Please wait while branch data is updating.",
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      $.ajax({
        url: "functions/sync_branches.php",
        type: "POST",
        dataType: "json",

        success: function (res) {
          if (res.success) {
            table.ajax.reload(function () {
              Swal.fire({
                title: "Success!",
                text: res.message,
                icon: "success",
                timer: 1500,
                showConfirmButton: false,
              });
            }, false);
          } else {
            Swal.fire({
              title: "Sync Failed",
              text: res.message || "Something went wrong.",
              icon: "error",
            });
          }
        },

        error: function (xhr) {
          console.log(xhr.responseText);

          Swal.fire({
            title: "Server Error",
            text: "Sync failed. Check console/network.",
            icon: "error",
          });
        },

        complete: function () {
          btn.prop("disabled", false).html("⟳ Sync Branches");
        },
      });
    });
  });
});

// =========================
// CHANGE STATUS SWITCH
// =========================
$(document).on("change", ".branch-status-switch", function () {
  const toggle = $(this);
  const code = toggle.data("code");
  const status = toggle.is(":checked") ? 1 : 0;

  toggle.prop("disabled", true);

  $.ajax({
    url: "functions/update_branch_status.php",
    type: "POST",
    dataType: "json",

    data: {
      branch_code: code,
      status: status,
    },

    success: function (res) {
      if (res.success) {
        const row = table.row(toggle.closest("tr"));
        const rowData = row.data();

        const statusText = status == 1 ? "active" : "inactive";

        // ✅ update row safely
        row
          .data({
            ...rowData,
            status: statusText,
          })
          .invalidate();

        Swal.fire({
          icon: "success",
          title: "Updated",
          text: `Branch status changed to ${statusText.toUpperCase()}`,
          timer: 1200,
          showConfirmButton: false,
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Update Failed",
          text: res.message || "Something went wrong.",
        });

        toggle.prop("checked", !toggle.is(":checked"));
      }
    },

    error: function () {
      Swal.fire({
        icon: "error",
        title: "Server Error",
        text: "Failed to update status.",
      });

      toggle.prop("checked", !toggle.is(":checked"));
    },

    complete: function () {
      toggle.prop("disabled", false);
    },
  });
});
