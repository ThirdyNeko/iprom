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
      data: function (d) {
        d.name = $("#filterName").val();
      },
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

      // STATUS + SWITCH IN SAME COLUMN
      {
        data: null,
        width: "120px",
        className: "text-center",
        render: function (data, type, row) {
          const isActive = String(row.status).toLowerCase() === "active";

          const checked = isActive ? "checked" : "";

          return `
      <div class="d-flex align-items-center justify-content-center gap-1">

        <span class="badge ${isActive ? "bg-success" : "bg-secondary"}">
          ${isActive ? "Active" : "Inactive"}
        </span>

        <div class="form-check form-switch m-0">
          <input
            class="form-check-input branch-status-switch"
            type="checkbox"
            data-code="${row.branch_code}"
            ${checked}
          >
        </div>

      </div>
    `;
        },
      },
    ],
  });

  $("#filterName").on("input", function () {
    table.draw();
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

$(document).on("change", ".branch-status-switch", function () {
  const toggle = $(this);
  const code = toggle.data("code");
  const status = toggle.is(":checked") ? 1 : 0;

  toggle.prop("disabled", true);

  // =========================
  // PRE-CHECK ASSIGNMENT FIRST
  // =========================
  $.ajax({
    url: "functions/check_branch_assignment.php",
    type: "POST",
    dataType: "json",
    data: { branch_code: code },

    success: function (check) {
      if (check.blocked) {
        toggle.prop("checked", !toggle.is(":checked"));

        const brands = (check.brands || []).join(", ");

        Swal.fire({
          icon: "warning",
          title: "Unable to update status for this branch.",
          text: brands
            ? `A promodiser is currently assigned and must be removed or reassigned before proceeding.`
            : "This branch has active assignments.",
          // Assigned to: ${brands}
        });

        toggle.prop("disabled", false);
        return;
      }

      // =========================
      // PROCEED WITH ORIGINAL UPDATE
      // =========================
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

              row
                .data({
                  ...rowData,
                  status: status === 1 ? "active" : "inactive",
                })
                .invalidate();
            }

            Swal.fire({
              icon: "success",
              title: "Updated",
              text: `Status changed to ${status === 1 ? "ACTIVE" : "INACTIVE"}`,
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
    },

    error: function () {
      toggle.prop("checked", !toggle.is(":checked"));

      Swal.fire({
        icon: "error",
        title: "Server Error",
        text: "Failed to validate branch assignments.",
      });

      toggle.prop("disabled", false);
    },
  });
});
