$(document).ready(function () {
  let branchMap = {};

  // 1. LOAD BRANCH LOOKUP FIRST
  $.getJSON("functions/get_branches.php", function (res) {
    branchMap = res;

    // 2. ONLY AFTER DATA IS READY → INIT TABLE
    initTable();
  });
  function applyFiltersFromURL() {
    const params = new URLSearchParams(window.location.search);

    if (params.get("branch")) $("#filterBranch").val(params.get("branch"));
    if (params.get("brand")) $("#filterBrand").val(params.get("brand"));
    if (params.get("status")) $("#filterStatus").val(params.get("status"));
    if (params.get("from_date")) $("#filterFrom").val(params.get("from_date"));
    if (params.get("to_date")) $("#filterTo").val(params.get("to_date"));
  }

  applyFiltersFromURL();

  function initTable() {
    window.assignmentTable = $("#assignmentTable").DataTable({
      processing: true,
      serverSide: true,
      ordering: false,

      ajax: {
        url: "functions/fetch_assignments.php",
        type: "POST",
        data: function (d) {
          d.branch = $("#filterBranch").val();
          d.brand = $("#filterBrand").val();
          d.status = $("#filterStatus").val();
          d.from_date = $("#filterFrom").val();
          d.to_date = $("#filterTo").val();
        },
      },

      pageLength: 50,
      lengthMenu: [10, 25, 50, 100],
      responsive: true,
      dom: "lrtip",
      order: [[3, "desc"]],

      columns: [
        {
          data: 0,
          render: function (data, type, row) {
            if (type === "display") {
              return branchMap[data] || data;
            }
            return data; // 👈 keeps BACD for logic
          },
        },
        { data: 1 }, // brand
        { data: 2 }, // required
        { data: 3 }, // assigned
        { data: 4 }, // status
        { data: 5 }, // updated_at
        { data: 6 }, // updated_by
      ],
    });
  }

  // =========================
  // FILTER CHANGE
  // =========================
  $("#filterBranch,#filterBrand,#filterStatus,#filterFrom,#filterTo").on(
    "change",
    function () {
      window.assignmentTable.ajax.reload();
    },
  );

  // =========================
  // ROW CLICK (FIXED)
  // =========================
  $("#assignmentTable tbody").on("click", "tr", function () {
    const rowData = window.assignmentTable.row(this).data();
    if (!rowData) return;

    // ✅ ALWAYS raw BACD (safe because of render fix above)
    const branch = rowData[0];
    const brand = rowData[1];

    const required = parseInt($(rowData[2]).text()) || 0;
    const assigned = parseInt(rowData[3]) || 0;
    const updated = rowData[5];
    const updatedBy = rowData[6];

    if (!branch || !brand) return;

    // store modal data (still BACD)
    $("#assignmentModal").data("branch", branch);
    $("#assignmentModal").data("brand", brand);

    // UI shows FULL NAME (already mapped or fallback)
    $("#modalBranch").text(branchMap[branch] || branch);
    $("#modalBrand").text(brand);

    $("#modalRequired").val(required);
    $("#modalStatus").html(getStatusBadge(required, assigned));

    $("#modalAssignedList").html(
      '<small class="text-muted">Loading...</small>',
    );

    bootstrap.Modal.getOrCreateInstance(
      document.getElementById("assignmentModal"),
    ).show();

    fetch("functions/get_assigned_promodizers.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ branch, brand }),
    })
      .then((res) => res.json())
      .then((res) => {
        if (res.status !== "success") {
          $("#modalAssignedList").html(
            '<div class="alert alert-danger">Failed to load</div>',
          );
          return;
        }

        let html = "";

        if (!res.data.length) {
          html = "<small>No employee assigned</small>";
        } else {
          html = '<ul class="list-group list-group-flush">';

          res.data.forEach((emp) => {
            html += `
            <li class="list-group-item d-flex justify-content-between align-items-center">
              ${emp.first_name} ${emp.last_name}
              <button class="btn btn-sm btn-primary edit-btn" data-id="${emp.id}">
                Edit
              </button>
            </li>
          `;
          });

          html += "</ul>";
        }

        const assignedCount = res.data.length;

        if (assignedCount < required) {
          html += `
          <div class="mt-2 text-left">
            <button type="button" class="btn btn-sm btn-primary add-promodizer-btn">
              + Add Promodiser
            </button>
          </div>
        `;
        }

        $("#modalAssignedList").html(html);
        $("#modalStatus").html(getStatusBadge(required, assignedCount));
      })
      .catch((err) => {
        console.error(err);
        $("#modalAssignedList").html(
          '<div class="alert alert-danger">Error loading data</div>',
        );
      });

    $("#modalUpdated").text(updated || "-");
    $("#modalUpdatedBy").text(updatedBy || "-");
  });
});
