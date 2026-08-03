$(document).ready(function () {
  /* ── USERS TABLE ── */
  var table = $("#usersTable").DataTable({
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    language: {
      emptyTable: "No data available",
      zeroRecords: "No Users match the selected filters",
    },
  });

  $("#filterStatus").on("change", function () {
    var val = this.value;
    table
      .column(3)
      .search(val ? "^" + val + "$" : "", true, false)
      .draw();
  });
  $("#filterPosition").on("keyup", function () {
    table.column(2).search(this.value).draw();
  });
  $("#filterUsername").on("keyup", function () {
    table.column(0).search(this.value).draw();
  });

  /* ── BRANCH MANAGERS TABLE ── */
  var tableBM = $("#usersTableBM").DataTable({
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    language: {
      emptyTable: "No data available",
      zeroRecords: "No Branch Managers match the selected filters",
    },
  });

  $("#filterStatusBM").on("change", function () {
    var val = this.value;
    tableBM
      .column(3)
      .search(val ? "^" + val + "$" : "", true, false)
      .draw();
  });
  $("#filterBranchBM").on("keyup", function () {
    tableBM.column(1).search(this.value).draw();
  });
  $("#filterUsernameBM").on("keyup", function () {
    tableBM.column(0).search(this.value).draw();
  });

  // recalc column widths once the Branch Managers tab is actually shown,
  // since DataTables mis-measures hidden panes on init
  $("#branch-managers-tab").on("shown.bs.tab", function () {
    tableBM.columns.adjust();
  });

  $(".clear-btn").on("click", function () {
    $(this).siblings("input").val("").trigger("keyup");
  });
});

/* ───────────────────────────────────────────
   USER STATUS SWITCH (delegated — works for both tables)
─────────────────────────────────────────── */
$(document).on("change", ".user-status-switch", function () {
  const toggle = $(this);
  const username = toggle.data("username");
  const newStatus = toggle.is(":checked") ? "ACTIVE" : "INACTIVE";
  const isEnable = newStatus === "ACTIVE";

  toggle.prop("disabled", true);

  Swal.fire({
    icon: isEnable ? "question" : "warning",
    title: `${isEnable ? "Enable" : "Disable"} User?`,
    html: `This will set <strong>${username}</strong> to <strong>${newStatus}</strong>.`,
    showCancelButton: true,
    confirmButtonText: "Yes",
    confirmButtonColor: isEnable ? "#198754" : "#dc3545",
  }).then((result) => {
    if (!result.isConfirmed) {
      toggle.prop("checked", !toggle.is(":checked"));
      toggle.prop("disabled", false);
      return;
    }

    $.ajax({
      url: "functions/update_user_status.php",
      type: "POST",
      data: { username, status: newStatus },
      dataType: "json",
      success: function (res) {
        if (res.success) {
          const $badge = toggle.closest("td").find(".badge");
          $badge
            .text(isEnable ? "Active" : "Inactive")
            .removeClass("bg-success bg-secondary")
            .addClass(isEnable ? "bg-success" : "bg-secondary");

          Swal.fire({
            icon: "success",
            title: `User ${newStatus === "ACTIVE" ? "Enabled" : "Disabled"}`,
            text: `${username} is now ${newStatus}.`,
            timer: 1500,
            showConfirmButton: false,
          });
        } else {
          toggle.prop("checked", !toggle.is(":checked"));
          Swal.fire(
            "Error",
            res.message || "Failed to update status.",
            "error",
          );
        }
      },
      error: function () {
        toggle.prop("checked", !toggle.is(":checked"));
        Swal.fire("Error", "Request failed.", "error");
      },
      complete: function () {
        toggle.prop("disabled", false);
      },
    });
  });
});
