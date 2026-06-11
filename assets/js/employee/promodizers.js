$(document).ready(function () {
  var table = $("#promodizerTable").DataTable({
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    autoWidth: false, // 👈 IMPORTANT
    ordering: false,

    language: {
      emptyTable: "No data available",
      zeroRecords: "No promodisers match the selected filters",
    },

    columnDefs: [
      { targets: 0, width: "20%" }, // Name (bigger)
      { targets: 6, width: "12%" }, // Assignment Date (smaller)
      { targets: 7, width: "12%" }, // Assigned By (smaller)
    ],
  });

  // =========================
  // STATUS FILTER HANDLER FIRST
  // =========================
  $("#filterStatus").on("change", function () {
    var val = this.value;

    if (val === "ACTIVE" || val === "INACTIVE") {
      table.column(3).search("^" + val + "$", true, false);
    } else {
      table.column(3).search("");
    }

    table.draw();
  });

  // =========================
  // DEFAULT = ACTIVE
  // =========================
  $("#filterStatus").val("ACTIVE").trigger("change");

  // =========================
  // URL PARAM SUPPORT
  // =========================
  const params = new URLSearchParams(window.location.search);
  const statusParam = params.get("status");
  const editId = params.get("edit");
  const addParam = params.get("add");

  // =========================
  // EDIT FLOW
  // =========================
  if (editId) {
    table.on("draw.dt", function () {
      const row = table
        .rows()
        .nodes()
        .to$()
        .filter(function () {
          return String($(this).data("id")) === String(editId);
        });

      if (!row.length) return;

      row.trigger("click");

      table.off("draw.dt");
    });

    table.draw(false);
  }
  // =========================
  // ADD FLOW (OPEN MODAL)
  // =========================
  if (addParam === "1") {
    setTimeout(() => {
      const modalEl = document.getElementById("addEmployeeModal");

      if (!modalEl) {
        console.error("addEmployeeModal not found");
        return;
      }

      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }, 200);
  }

  // =========================
  // STATUS PARAM
  // =========================
  if (statusParam) {
    $("#filterStatus").val(statusParam.toUpperCase()).trigger("change");
  }

  // =========================
  // NAME FILTER
  // =========================
  $("#filterName").on("input", function () {
    table.column(0).search(this.value).draw();
  });

  // =========================
  // BRANCH FILTER
  // =========================
  $("#filterBranch").on("change", function () {
    table.draw();
  });

  $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    var selectedBranch = $("#filterBranch").val();

    if (!selectedBranch) return true;

    var row = table.row(dataIndex).node();
    var branchCode = $(row).find("td[data-branch-code]").data("branch-code");

    return branchCode === selectedBranch;
  });

  $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    var area = $("#filterArea").val();
    var region = $("#filterRegion").val();

    // get row element
    var row = table.row(dataIndex).node();

    // branch_code must come from HTML (we will use data-branch-code)
    var branchCode = $(row).data("branch");

    if (!branchCode) return true;

    var info = branchMap[branchCode];

    if (!info) return true;

    if (area && info.area !== area) return false;
    if (region && info.region !== region) return false;

    return true;
  });

  $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    var company = ($("#filterCompany").val() || "").toLowerCase().trim();
    var agency = ($("#filterAgency").val() || "").toLowerCase().trim();

    var row = table.row(dataIndex).node();
    var branchCode = $(row).data("branch");

    var rowCompany = (branchMap[branchCode]?.corpo || "").toLowerCase().trim(); // ← derived from branch
    var rowAgency = ($(row).data("agency") || "").toLowerCase().trim();

    if (company && rowCompany !== company) return false;
    if (agency && rowAgency !== agency) return false;

    return true;
  });

  $("#filterCompany, #filterAgency").on("change", function () {
    table.draw();
  });

  $("#filterArea, #filterRegion").on("change", function () {
    table.draw();
  });

  // =========================
  // BRAND FILTER
  // =========================
  $("#filterBrand").on("change", function () {
    var val = this.value;
    table
      .column(2)
      .search(val ? "^" + val + "$" : "", true, false)
      .draw();
  });

  // =========================
  // ASSIGNED BY FILTER
  // =========================
  $("#filterAssignedBy").on("input", function () {
    table.column(7).search(this.value).draw();
  });

  // =========================
  // DATE FILTER
  // =========================
  $.fn.dataTable.ext.search.push(function (settings, data) {
    var from = $("#filterFrom").val();
    var to = $("#filterTo").val();
    var date = data[6];

    if (!date) return true;

    var rowDate = new Date(date);
    var fromDate = from ? new Date(from) : null;
    var toDate = to ? new Date(to) : null;

    return (!fromDate || rowDate >= fromDate) && (!toDate || rowDate <= toDate);
  });

  $("#filterFrom, #filterTo").on("change", function () {
    table.draw();
  });

  // =========================
  // EMPLOYMENT STATUS FILTER
  // =========================
  $("#filterEmploymentStatus").on("change", function () {
    var val = this.value;
    table
      .column(4)
      .search(val ? "^" + escapeRegex(val) + "$" : "", true, false)
      .draw();
  });

  // =========================
  // SUB STATUS FILTER
  // =========================
  $("#filterSubStatus").on("change", function () {
    var val = this.value;
    table
      .column(5)
      .search(val ? "^" + escapeRegex(val) + "$" : "", true, false)
      .draw();
  });

  function escapeRegex(val) {
    return val.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }
});

function formatDate(dateStr) {
  if (!dateStr) return "";

  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr; // fallback if already formatted

  const month = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  const year = d.getFullYear();

  return `${month}/${day}/${year}`;
}

$(document).on("click", ".clickable-row", function () {
  // BASIC INFO
  $("#editFirstName").val($(this).data("first-name"));
  $("#editMiddleName").val($(this).data("middle-name"));
  $("#editLastName").val($(this).data("last-name"));
  $("#editSuffix").val($(this).data("suffix"));

  // DROPDOWNS
  $("#editBranch").val($(this).data("branch"));
  $("#editBrand").val($(this).data("brand"));
  $("#editAgency").val($(this).data("agency"));

  $("#editEmploymentStatus").val($(this).data("employment-status"));
  $("#editSubStatus").val($(this).data("sub-status"));

  // TEXT FIELDS
  $("#editCorpo").val($(this).data("corpo"));
  $("#editGender").val($(this).data("gender"));
  $("#editStatus").val($(this).data("status"));

  // DATES
  $("#editAssignmentDate").val($(this).data("assignment-date"));
  $("#editDateHired").val($(this).data("date-hired"));
  $("#editDateLastUpdated").val($(this).data("date-last-updated"));
  $("#editBirthdate").val($(this).data("birthdate"));

  // USERS
  $("#editLastAssignedBy").val($(this).data("last-assigned-by"));
  $("#editLastUpdatedBy").val($(this).data("last-updated-by"));

  // OPEN MODAL
  $("#editPromodizerModal").modal("show");
});

document.getElementById("exportExcel").addEventListener("click", function () {

  // reverse lookup: branch name → branchMap entry
  const branchByName = Object.fromEntries(
    Object.values(branchMap).map(b => [b.branch, b])
  );

  const filters = {
    branch: $("#filterBranch").val(),
    brand: $("#filterBrand").val(),
    status: $("#filterStatus").val(),
    employment_status: $("#filterEmploymentStatus").val(),
    sub_status: $("#filterSubStatus").val(),
    agency: $("#filterAgency").val(),
    // corpo removed — filtered client-side via branchMap
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

      // ← filter corpo client-side using branchMap
      const corpoFilter = ($("#filterCompany").val() || "").toLowerCase().trim();
      if (corpoFilter) {
        data = data.filter(p => {
          const rowCorpo = (branchByName[p.branch]?.corpo || "").toLowerCase().trim();
          return rowCorpo === corpoFilter;
        });
      }

      // warn if large export
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
          (branchByName[p.branch]?.corpo || "").toUpperCase(), // ← Company column
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
