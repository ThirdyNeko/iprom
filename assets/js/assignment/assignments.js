$(document).ready(function () {
  let branchMap = {};

  // 1. LOAD BRANCH LOOKUP FIRST
  $.getJSON("functions/get_branches.php", function (res) {
    branchMap = res;
    initTable(); // always init — __NO_ACCESS__ handles empty branch case
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
          if (isStaff && sessionBranches.length === 0) {
            d.branch = "__NO_ACCESS__";
            return;
          }
          d.branch = $("#filterBranch").val();
          d.brand = $("#filterBrand").val();
          d.status = $("#filterStatus").val();
          d.from_date = $("#filterFrom").val();
          d.to_date = $("#filterTo").val();
        },
      },
      language: {
        emptyTable: "No data available",
        zeroRecords: "No assignments match the selected filters",
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

document
  .getElementById("exportExcel")
  .addEventListener("click", async function () {
    const table = $("#assignmentTable").DataTable();
    const rows = table.rows({ search: "applied" }).nodes();

    // warn if large export
    if ($(rows).length > 1000) {
      const proceed = await Swal.fire({
        title: "Large Export",
        text: `You're about to export ${$(rows).length} rows. This may take a moment. Continue?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, export",
        cancelButtonText: "Cancel",
      });

      if (!proceed.isConfirmed) return;
    }

    let exportData = [];

    // headers
    let headers = [];
    $("#assignmentTable thead th").each(function () {
      headers.push($(this).text().trim());
    });

    exportData.push(headers);

    // rows
    $(rows).each(function () {
      let row = [];

      $(this)
        .find("td")
        .each(function () {
          row.push($(this).text().trim());
        });

      exportData.push(row);
    });

    // worksheet
    const ws = XLSX.utils.aoa_to_sheet(exportData);

    // auto column width
    ws["!cols"] = exportData[0].map((_, i) => {
      let max = 10;

      exportData.forEach((r) => {
        const val = r[i] ? r[i].toString() : "";
        max = Math.max(max, val.length);
      });

      return { wch: max + 2 };
    });

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Assignments");

    XLSX.writeFile(wb, "Assignment_Overview.xlsx");
  });
