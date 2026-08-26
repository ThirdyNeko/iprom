function escapeHtml(str) {
  return $("<div>")
    .text(str ?? "")
    .html();
}

function statusCellHtml(row) {
  const isActive = row.status === "active";
  return `
    <div class="d-flex align-items-center justify-content-center gap-2">
      <span class="badge ${isActive ? "bg-success" : "bg-secondary"}">
        ${isActive ? "Active" : "Inactive"}
      </span>
      <div class="form-check form-switch m-0">
        <input class="form-check-input user-status-switch"
               type="checkbox"
               data-id="${escapeHtml(row.id)}"
               data-username="${escapeHtml(row.username)}"
               ${isActive ? "checked" : ""}>
      </div>
    </div>`;
}

function actionsCellHtml(row) {
  return `
    <button class="btn btn-sm btn-success view-user" data-id="${escapeHtml(row.id)}">
      Update
    </button>
    <button class="btn btn-sm btn-primary view-user view-user-readonly" data-id="${escapeHtml(row.id)}">
      View
    </button>`;
}

function userLikeColumns() {
  return [
    { data: "username", render: (d) => escapeHtml(d) },
    { data: "role_label", render: (d) => escapeHtml(d) },
    { data: "position", render: (d) => escapeHtml(d) },
    {
      data: "status",
      render: (d, type, row) => (type === "display" ? statusCellHtml(row) : d),
    },
    {
      data: null,
      orderable: false,
      searchable: false,
      render: (d, type, row) => actionsCellHtml(row),
    },
  ];
}

$(document).ready(function () {
  /* ── USERS TABLE (HR tab, server-side) ── */
  if ($("#usersTable").length) {
    var table = $("#usersTable").DataTable({
      serverSide: true,
      processing: true,
      pageLength: 25,
      responsive: true,
      dom: "lrtip",
      ajax: {
        url: "functions/get_users_list.php",
        type: "GET",
        data: function (d) {
          d.scope = "users";
        },
      },
      columns: userLikeColumns(),
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
  }

  /* ── BRANCH MANAGERS TABLE (server-side) ── */
  if ($("#usersTableBM").length) {
    var tableBM = $("#usersTableBM").DataTable({
      serverSide: true,
      processing: true,
      pageLength: 25,
      responsive: true,
      dom: "lrtip",
      ajax: {
        url: "functions/get_users_list.php",
        type: "GET",
        data: function (d) {
          d.scope = "bm";
        },
      },
      columns: [
        { data: "username", render: (d) => escapeHtml(d) },
        { data: "branch", render: (d) => escapeHtml(d) },
        { data: "position", render: (d) => escapeHtml(d) },
        {
          data: "status",
          render: (d, type, row) =>
            type === "display" ? statusCellHtml(row) : d,
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: (d, type, row) => actionsCellHtml(row),
        },
      ],
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
  }

  /* ── AUDIT TABLE (server-side) ── */
  if ($("#usersTableAudit").length) {
    var tableAudit = $("#usersTableAudit").DataTable({
      serverSide: true,
      processing: true,
      pageLength: 25,
      responsive: true,
      dom: "lrtip",
      ajax: {
        url: "functions/get_users_list.php",
        type: "GET",
        data: function (d) {
          d.scope = "audit";
        },
      },
      columns: userLikeColumns(),
      language: {
        emptyTable: "No data available",
        zeroRecords: "No Audit users match the selected filters",
      },
    });

    $("#filterStatusAudit").on("change", function () {
      var val = this.value;
      tableAudit
        .column(3)
        .search(val ? "^" + val + "$" : "", true, false)
        .draw();
    });
    $("#filterPositionAudit").on("keyup", function () {
      tableAudit.column(2).search(this.value).draw();
    });
    $("#filterUsernameAudit").on("keyup", function () {
      tableAudit.column(0).search(this.value).draw();
    });

    // recalc column widths once the Audit tab is actually shown
    // (no-op if Audit is the default/only visible tab, harmless either way)
    $("#audit-tab").on("shown.bs.tab", function () {
      tableAudit.columns.adjust();
    });
  }

  $(".clear-btn").on("click", function () {
    $(this).siblings("input").val("").trigger("keyup");
  });
});

/* ───────────────────────────────────────────
   USER STATUS SWITCH (delegated — works for all three tables)
─────────────────────────────────────────── */
$(document).on("change", ".user-status-switch", function () {
  const toggle = $(this);
  const id = toggle.data("id");
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
      data: { id, status: newStatus },
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
