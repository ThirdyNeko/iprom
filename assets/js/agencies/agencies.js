var table;

$(document).ready(function () {
  table = $("#Brandtable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    ordering: false,

    ajax: {
      url: "functions/fetch_brands.php",
      type: "POST",
    },

    columns: [
      { data: "brand" },
      // AGENCY
      {
        data: "agency",
        render: function (data) {
          const value = (data ?? "").toString().toUpperCase();

          return `
            <input
              type="text"
              class="form-control form-control-sm agency-input text-uppercase"
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
                  class="form-check-input brand-status-switch"
                  type="checkbox"
                  data-id="${row.id}"
                  ${checked}
                >
              </div>

              <button
                class="btn btn-sm btn-primary update-brand-btn"
                data-id="${row.id}"
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
  $(document).on("input", ".agency-input", function () {
    this.value = this.value.toUpperCase();
  });

  // =========================
  // SYNC BRANDS
  // =========================
  $(document).on("click", "#syncBrandsBtn", function () {
    const btn = $(this);

    Swal.fire({
      title: "Sync Brands?",
      text: "This will sync brands from the source system.",
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
        url: "functions/sync_brands.php",
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
          btn.prop("disabled", false).html("⟳ Sync Brands");
        },
      });
    });
  });
});

// =========================
// CHANGE STATUS SWITCH
// =========================
$(document).on("change", ".brand-status-switch", function () {
  const toggle = $(this);
  const id = toggle.data("id");
  const status = toggle.is(":checked") ? 1 : 0;

  toggle.prop("disabled", true);

  $.ajax({
    url: "functions/update_brand_status.php",
    type: "POST",
    dataType: "json",
    data: {
      id: id,
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
          text: `Brand status changed to ${status === 1 ? "ACTIVE" : "INACTIVE"}`,
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
$(document).on("click", ".update-brand-btn", function () {
  const id = $(this).data("id");

  const rowNode = table.row($(this).closest("tr")).node();

  const agency = $(rowNode).find(".agency-input").val();
  const status = $(rowNode).find(".brand-status-switch").is(":checked") ? 1 : 0;

  $.ajax({
    url: "functions/update_brand.php",
    type: "POST",
    dataType: "json",
    data: {
      id: id,
      agency: agency,
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
