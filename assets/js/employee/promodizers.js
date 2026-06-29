$(document).ready(function () {
  // =========================
  // URL PARAMS (read before table init so ajax.data picks them up)
  // =========================
  const params = new URLSearchParams(window.location.search);
  const statusParam = params.get("status");
  const editId = params.get("edit");
  const addParam = params.get("add");

  // Pre-set status dropdown so the very first ajax.data call uses it
  if (statusParam) {
    $("#filterStatus").val(statusParam.toUpperCase());
  }
  // Default to ACTIVE if no status param (HTML already has selected, but be explicit)
  if (!statusParam && !$("#filterStatus").val()) {
    $("#filterStatus").val("ACTIVE");
  }

  // =========================
  // DATATABLE — SERVER SIDE
  // =========================
  var table = $("#promodizerTable").DataTable({
    serverSide: true,
    processing: true,
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    autoWidth: false,
    ordering: false,

    language: {
      emptyTable: "No data available",
      zeroRecords: "No promodisers match the selected filters",
      processing: "Loading...",
    },

    ajax: {
      url: "functions/get_promodizers.php",
      type: "GET",
      data: function (d) {
        d.name_search = $("#filterName").val();
        d.branch = $("#filterBranch").val();
        d.brand = $("#filterBrand").val();
        d.status = $("#filterStatus").val();
        d.employment_status = $("#filterEmploymentStatus").val();
        d.sub_status = $("#filterSubStatus").val();
        d.assigned_by = $("#filterAssignedBy").val();
        d.from_date = $("#filterFrom").val();
        d.to_date = $("#filterTo").val();
        d.corpo = $("#filterCompany").val();
        d.agency = $("#filterAgency").val();
        d.region = $("#filterRegion").val();
        d.area = $("#filterArea").val();
        return d;
      },
      error: function (xhr, error, thrown) {
        console.error("DataTable AJAX error:", error, thrown);
      },
    },

    columns: [
      {
        // Name
        data: null,
        render: function (d) {
          const parts = [d.first_name, d.last_name];
          if (d.suffix && d.suffix.trim()) parts.push(d.suffix.trim());
          return parts.join(" ");
        },
      },
      { data: "branch_display", defaultContent: "-" }, // Branch
      { data: "brand", defaultContent: "-" }, // Brand
      { data: "status", defaultContent: "-" }, // Status
      { data: "employment_status", defaultContent: "-" }, // Employment Status
      { data: "sub_status", defaultContent: "-" }, // Sub-Status
      {
        // Assignment Date
        data: "assignment_date",
        render: function (d) {
          return d ? formatDate(d) : "-";
        },
      },
      { data: "last_assigned_by", defaultContent: "-" }, // Last Assigned By
    ],

    // Set data-* attributes on each row for the modal click handler
    createdRow: function (row, data) {
      $(row).addClass("clickable-row");
      $(row).attr("data-id", data.id);
      $(row).attr("data-branch", data.branch); // branch_code
      $(row).attr("data-company", data.corpo || "");
      $(row).attr("data-agency", data.agency || "");
    },

    columnDefs: [
      { targets: 0, width: "20%" },
      { targets: 6, width: "12%" },
      { targets: 7, width: "12%" },
    ],
  });

  // =========================
  // SHARED RELOAD HELPER
  // =========================
  function reloadTable() {
    table.ajax.reload(null, false); // false = keep current page
  }

  // =========================
  // NAME FILTER (debounced)
  // =========================
  var nameTimer;
  $("#filterName").on("input", function () {
    clearTimeout(nameTimer);
    nameTimer = setTimeout(reloadTable, 400);
  });

  // =========================
  // ASSIGNED BY FILTER (debounced)
  // =========================
  var assignedByTimer;
  $("#filterAssignedBy").on("input", function () {
    clearTimeout(assignedByTimer);
    assignedByTimer = setTimeout(reloadTable, 400);
  });

  // =========================
  // ALL SELECT FILTERS
  // =========================
  $(
    "#filterBranch, #filterBrand, #filterStatus, #filterEmploymentStatus, " +
      "#filterSubStatus, #filterCompany, #filterAgency, " +
      "#filterArea, #filterRegion, #filterFrom, #filterTo",
  ).on("change", reloadTable);

  // =========================
  // EDIT FLOW — open modal directly via openEmployeeModal()
  // =========================
  if (editId) {
    // Wait briefly for modal JS (edit_promodizer_modal.js) to finish setup
    setTimeout(function () {
      if (typeof openEmployeeModal === "function") {
        openEmployeeModal(editId);
      }
    }, 150);
  }

  // =========================
  // ADD FLOW — open add modal
  // =========================
  if (addParam === "1") {
    setTimeout(function () {
      var modalEl = document.getElementById("addEmployeeModal");
      if (!modalEl) {
        console.error("addEmployeeModal not found");
        return;
      }
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }, 200);
  }
});

// =========================
// FORMAT DATE HELPER
// =========================
function formatDate(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  const year = d.getFullYear();
  return `${month}/${day}/${year}`;
}

// =========================
// EXPORT (unchanged — uses its own fetch endpoint)
// =========================
document.getElementById("exportExcel").addEventListener("click", function () {
  const branchByName = Object.fromEntries(
    Object.values(branchMap).map((b) => [b.branch, b]),
  );

  const filters = {
    branch: $("#filterBranch").val(),
    brand: $("#filterBrand").val(),
    status: $("#filterStatus").val(),
    employment_status: $("#filterEmploymentStatus").val(),
    sub_status: $("#filterSubStatus").val(),
    agency: $("#filterAgency").val(),
    assigned_by: $("#filterAssignedBy").val(),
    region: $("#filterRegion").val(),
    area: $("#filterArea").val(),
    search: $("#filterName").val(),
    from_date: $("#filterFrom").val(),
    to_date: $("#filterTo").val(),
  };

  fetch("functions/get_promodizers_export.php?" + new URLSearchParams(filters))
    .then((res) => res.json())
    .then(async (data) => {
      // Filter corpo client-side using branchMap (export endpoint doesn't expose it)
      const corpoFilter = ($("#filterCompany").val() || "")
        .toLowerCase()
        .trim();
      if (corpoFilter) {
        data = data.filter((p) => {
          const rowCorpo = (branchByName[p.branch]?.corpo || "")
            .toLowerCase()
            .trim();
          return rowCorpo === corpoFilter;
        });
      }

      if (data.length > 1000) {
        const proceed = await Swal.fire({
          title: "Large Export",
          text: `You're about to export ${data.length} rows. This may take a moment. Continue?`,
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Yes, export",
          cancelButtonText: "Cancel",
        });
        if (!proceed.isConfirmed) return;
      }

      let exportData = [
        [
          "First Name",
          "Middle Name",
          "Last Name",
          "Suffix",
          "Gender",
          "Birthdate",
          "Date Hired",
          "Branch",
          "Brand",
          "Status",
          "Employment Status",
          "Sub-Status",
          "Agency",
          "Company",
          "Assignment Date",
          "Last Assigned By",
        ],
      ];

      data.forEach((p) => {
        exportData.push([
          p.first_name,
          p.middle_name,
          p.last_name,
          p.suffix,
          p.gender,
          formatDate(p.birthday),
          formatDate(p.date_hired),
          p.branch,
          p.brand,
          p.status,
          p.employment_status,
          p.sub_status,
          p.agency,
          (branchByName[p.branch]?.corpo || "").toUpperCase(),
          formatDate(p.assignment_date),
          p.last_assigned_by,
        ]);
      });

      const ws = XLSX.utils.aoa_to_sheet(exportData);

      const colWidths = exportData[0].map((_, i) => {
        let max = 10;
        exportData.forEach((r) => {
          max = Math.max(max, (r[i] || "").toString().length);
        });
        return { wch: max + 2 };
      });

      ws["!cols"] = colWidths;

      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, "Promodizers");
      XLSX.writeFile(wb, "Promodizer_Overview.xlsx");
    });
});
