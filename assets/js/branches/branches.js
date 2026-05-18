$(document).ready(function () {
  let table = $("#Branchtable").DataTable({
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

      {
        data: "status",
        render: function (data, type, row) {
          const checked =
            String(data).toLowerCase() === "active" ? "checked" : "";

          return `
        <div class="form-check form-switch d-flex justify-content-center">
          <input
            class="form-check-input branch-status-switch"
            type="checkbox"
            role="switch"
            data-code="${row.branch_code}"
            ${checked}
          >
        </div>
      `;
        },
      },
    ],
  });

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

      // SHOW LOADING SCREEN
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
            // WAIT FOR DATATABLE TO FINISH RELOADING
            table.ajax.reload(function () {
              Swal.fire({
                title: "Success!",
                text: res.message,
                icon: "success",
                timer: 2000,
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

// CHANGE STATUS
$(document).on("change", ".branch-status-switch", function () {
  const toggle = $(this);

  const code = toggle.data("code");

  const status = toggle.is(":checked") ? 1 : 0;

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
        const statusText = status == 1 ? "ACTIVE" : "INACTIVE";

        Swal.fire({
          icon: "success",
          title: "Updated",
          text: `Branch status changed to ${statusText}`,
          timer: 1500,
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
  });
});
