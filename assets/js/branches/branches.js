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
      {
        data: "corpo",
        render: function (data) {
          return (data ?? "").toString().toUpperCase();
        },
      },
      { data: "region" },
      { data: "area" },

      // DIRECTOR
      {
        data: "director",
        render: function (data) {
          const value = (data ?? "").toString().toUpperCase();

          return `
            <input
              type="text"
              class="form-control form-control-sm director-input text-uppercase"
              value="${value}"
            >
          `;
        },
      },

      // STATUS TEXT ONLY
      {
        data: "status",
        render: function (data) {
          const isActive = String(data).toLowerCase() === "active";

          return `
            <span class="badge ${isActive ? "bg-success" : "bg-secondary"}">
              ${isActive ? "Active" : "Inactive"}
            </span>
          `;
        },
      },

      // ACTIONS
      {
        data: null,
        orderable: false,
        searchable: false,

        render: function (data, type, row) {
          const isActive = String(row.status).toLowerCase() === "active";
          const checked = isActive ? "checked" : "";

          return `
            <div class="d-flex align-items-center justify-content-center gap-2">

              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input branch-status-switch"
                  type="checkbox"
                  data-code="${row.branch_code}"
                  ${checked}
                >
              </div>

              <button
                class="btn btn-sm btn-primary update-branch-btn"
                data-code="${row.branch_code}"
              >
                Update
              </button>

            </div>
          `;
        },
      },
    ],
  });

  // =========================
  // FORCE UPPERCASE INPUT
  // =========================
  $(document).on("input", ".director-input", function () {
    this.value = this.value.toUpperCase();
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
    }).then((result) => {
      if (!result.isConfirmed) return;

      btn.prop("disabled", true);

      Swal.fire({
        title: "Syncing...",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });

      $.ajax({
        url: "functions/sync_branches.php",
        type: "POST",
        dataType: "json",

        success: function (res) {
          if (res.success) {
            table.ajax.reload(function () {
              Swal.fire({
                icon: "success",
                title: "Success",
                text: res.message,
                timer: 1500,
                showConfirmButton: false,
              });
            }, false);
          } else {
            Swal.fire("Error", res.message, "error");
          }
        },

        error: function () {
          Swal.fire("Error", "Server error", "error");
        },

        complete: function () {
          btn.prop("disabled", false).html("⟳ Sync Branches");
        },
      });
    });
  });
});

// =========================
// STATUS SWITCH (SEPARATE)
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
      if (!res.success) {
        toggle.prop("checked", !toggle.is(":checked"));
        Swal.fire("Error", res.message, "error");
      }
    },

    error: function () {
      toggle.prop("checked", !toggle.is(":checked"));
      Swal.fire("Error", "Server error", "error");
    },

    complete: function () {
      toggle.prop("disabled", false);
    },
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

        if (row) {
          const rowData = row.data();

          const statusText = status === 1 ? "active" : "inactive";

          row
            .data({
              ...rowData,
              status: statusText,
            })
            .invalidate();
        }

        Swal.fire({
          icon: "success",
          title: "Updated",
          text: `Branch status changed to ${status === 1 ? "ACTIVE" : "INACTIVE"}`,
          timer: 1200,
          showConfirmButton: false,
        });
      } else {
        toggle.prop("checked", !toggle.is(":checked"));

        Swal.fire({
          icon: "error",
          title: "Update Failed",
          text: res.message || "Something went wrong.",
        });
      }
    },

    error: function () {
      toggle.prop("checked", !toggle.is(":checked"));

      Swal.fire({
        icon: "error",
        title: "Server Error",
        text: "Failed to update status.",
      });
    },

    complete: function () {
      toggle.prop("disabled", false);
    },
  });
});

// =========================
// UPDATE BUTTON (SEPARATE)
// =========================
$(document).on("click", ".update-branch-btn", function () {
  const code = $(this).data("code");
  const rowNode = table.row($(this).closest("tr")).node();

  const director = $(rowNode).find(".director-input").val();

  Swal.fire({
    title: "Confirm Update",
    text: "Save changes to this branch?",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Update",
    cancelButtonText: "Cancel",
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.ajax({
      url: "functions/update_branch.php",
      type: "POST",
      dataType: "json",
      data: {
        branch_code: code,
        director: director,
      },

      success: function (res) {
        if (res.success) {
          Swal.fire({
            icon: "success",
            title: "Updated",
            timer: 1200,
            showConfirmButton: false,
          });

          table.ajax.reload(null, false);
        } else {
          Swal.fire("Error", res.message, "error");
        }
      },

      error: function () {
        Swal.fire("Error", "Server error", "error");
      },
    });
  });
});
