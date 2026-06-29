$(document).ready(function () {
  // =========================
  // DATATABLE
  // =========================
  window.table = $("#brandTable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    ordering: false,
    responsive: true,
    dom: "lrtip",

    ajax: {
      url: "functions/fetch_brands.php",
      type: "POST",
      data: function (d) {
        d.name = $("#filterName").val();
      },
    },

    columns: [
      { data: "brand" },

      // STATUS SWITCH
      {
        data: null,
        width: "150px",
        className: "text-center",
        render: function (data, type, row) {
          const isActive = String(row.status) === "1";

          return `
            <div class="d-flex align-items-center justify-content-center gap-1">

              <span class="badge ${isActive ? "bg-success" : "bg-secondary"}">
                ${isActive ? "Active" : "Inactive"}
              </span>

              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input brand-status-switch"
                  type="checkbox"
                  data-id="${row.id}"
                  ${isActive ? "checked" : ""}
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
  // RESET ON MODAL CLOSE
  // =========================
  $("#brandModal").on("hidden.bs.modal", function () {
    $("#brandId").val("");
    $("#brandName").val("");
  });

  // =========================
  // FORCE UPPERCASE
  // =========================
  $(document).on("input", "#brandName", function () {
    this.value = this.value.toUpperCase();
  });

  // =========================
  // SAVE
  // =========================
  $("#brandForm").on("submit", function (e) {
    e.preventDefault();

    const id = $("#brandId").val();
    const brand = $("#brandName").val()?.trim().toUpperCase() || "";
    const isEdit = id && id !== "";

    if (!brand) {
      Swal.fire({
        icon: "warning",
        title: "Required",
        text: "Brand name is required.",
      });
      return;
    }

    Swal.fire({
      title: isEdit ? "Update this brand?" : "Add this brand?",
      text: brand,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#2d68c4",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes",
    }).then((result) => {
      if (!result.isConfirmed) return;

      submitBrand(id, brand, isEdit);
    });
  });

  // =========================
  // AJAX SAVE
  // =========================
  function submitBrand(id, brand, isEdit) {
    $.ajax({
      url: "functions/save_brand.php",
      type: "POST",
      dataType: "json",
      data: { id, brand },

      beforeSend: function () {
        Swal.fire({
          title: isEdit ? "Updating..." : "Saving...",
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading(),
        });
      },

      success: function (res) {
        Swal.close();

        if (res.success) {
          Swal.fire({
            icon: "success",
            title: isEdit ? "Updated!" : "Added!",
            text: res.message,
            timer: 1200,
            showConfirmButton: false,
          });

          $("#brandModal").modal("hide");
          window.table.ajax.reload(null, false);
        } else {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: res.message || "Operation failed.",
          });
        }
      },

      error: function () {
        Swal.close();

        Swal.fire({
          icon: "error",
          title: "Server Error",
          text: "Something went wrong.",
        });
      },
    });
  }
});

// =========================
// STATUS SWITCH (BRAND)
// =========================
$(document).on("change", ".brand-status-switch", function () {
  const toggle = $(this);
  const id = toggle.data("id");
  const status = toggle.is(":checked") ? 1 : 0;

  const rowData = window.table.row(toggle.closest("tr")).data();
  const brand = rowData?.brand;

  toggle.prop("disabled", true);

  Swal.fire({
    title: `${status === 1 ? "Activate" : "Deactivate"} this brand?`,
    text: brand,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#2d68c4",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Yes",
  }).then((result) => {
    if (!result.isConfirmed) {
      toggle.prop("checked", !toggle.is(":checked"));
      toggle.prop("disabled", false);
      return;
    }

    $.ajax({
      url: "functions/update_brand_status.php",
      type: "POST",
      dataType: "json",
      data: { id, status },

      success: function (res) {
        if (res.success) {
          const row = window.table.row(toggle.closest("tr"));

          if (row) {
            row.data({ ...row.data(), status: status.toString() }).invalidate();
          }

          Swal.fire({
            icon: "success",
            title: "Updated",
            text: `Brand is now ${status === 1 ? "Active" : "Inactive"}`,
            timer: 1200,
            showConfirmButton: false,
          });
        } else {
          toggle.prop("checked", !toggle.is(":checked"));
          Swal.fire({
            icon: "error",
            title: "Update Failed",
            text: res.message,
          });
        }
      },

      error: function () {
        toggle.prop("checked", !toggle.is(":checked"));
        Swal.fire({ icon: "error", title: "Server Error" });
      },

      complete: function () {
        toggle.prop("disabled", false);
      },
    });
  });
});
