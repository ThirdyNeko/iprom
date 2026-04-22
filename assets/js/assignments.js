$(document).ready(function () {
  function applyFiltersFromURL() {
    const params = new URLSearchParams(window.location.search);

    if (params.get("branch")) $("#filterBranch").val(params.get("branch"));
    if (params.get("brand")) $("#filterBrand").val(params.get("brand"));
    if (params.get("status")) $("#filterStatus").val(params.get("status"));
    if (params.get("from_date")) $("#filterFrom").val(params.get("from_date"));
    if (params.get("to_date")) $("#filterTo").val(params.get("to_date"));
  }

  applyFiltersFromURL();

  window.assignmentTable = $("#assignmentTable").DataTable({
    processing: true,
    serverSide: true,
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
    order: [[5, "desc"]], // FIXED (was 6, but safer depending on columns)
  });

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

    // because PHP returns ARRAY (not object)
    const branch = rowData[0];
    const brand = rowData[1];
    const required = parseInt($(rowData[2]).text()) || 0;
    const assigned = parseInt(rowData[3]) || 0;
    const updated = rowData[5];
    const updatedBy = rowData[6];

    if (!branch || !brand) return;

    // store modal data
    $("#assignmentModal").data("branch", branch);
    $("#assignmentModal").data("brand", brand);

    // fill modal (assumes your modal JS exists)
    $("#modalBranch").text(branch);
    $("#modalBrand").text(brand);
    $("#modalRequired").val(required);
    $("#modalStatus").html(getStatusBadge(required, assigned));

    $("#modalAssignedList").html(
      '<small class="text-muted">Loading...</small>',
    );

    // OPEN MODAL
    bootstrap.Modal.getOrCreateInstance(
      document.getElementById("assignmentModal"),
    ).show();

    // FETCH ASSIGNED
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
          html = "<small>No assigned employees</small>";
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
            <div class="mt-2 text-center">
              <a href="promodizers.php?status=inactive" class="btn btn-sm btn-primary">
                + Add Promodizer
              </a>
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

    // update date fields
    $("#modalUpdated").text(updated || "-");
    $("#modalUpdatedBy").text(updatedBy || "-");
  });
});
