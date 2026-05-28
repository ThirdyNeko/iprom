$(document).ready(function () {
  var table = $("#promodizerTable").DataTable({
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    autoWidth: false, // 👈 IMPORTANT
    ordering: false,

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

      row.addClass("table-warning");

      $("html, body").animate(
        {
          scrollTop: row.offset().top - 150,
        },
        300,
      );

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

    var rowCompany = ($(row).data("company") || "").toLowerCase().trim();
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

document.getElementById("exportExcel").addEventListener("click", function () {
  // Get DataTable instance
  const table = $("#promodizerTable").DataTable();

  // Get only filtered rows
  const data = table.rows({ search: "applied" }).nodes();

  // Create array
  let exportData = [];

  // Headers
  let headers = [];
  $("#promodizerTable thead th").each(function () {
    headers.push($(this).text().trim());
  });

  exportData.push(headers);

  // Rows
  $(data).each(function () {
    let row = [];

    $(this)
      .find("td")
      .each(function () {
        row.push($(this).text().trim());
      });

    exportData.push(row);
  });

  // Create worksheet
  const ws = XLSX.utils.aoa_to_sheet(exportData);

  // AUTO COLUMN WIDTH CALCULATION
  const colWidths = exportData[0].map((_, colIndex) => {
    let maxLength = 10;

    exportData.forEach((row) => {
      const cell = row[colIndex] ? row[colIndex].toString() : "";
      maxLength = Math.max(maxLength, cell.length);
    });

    return { wch: maxLength + 2 }; // padding
  });

  ws["!cols"] = colWidths;

  // Create workbook
  const wb = XLSX.utils.book_new();

  XLSX.utils.book_append_sheet(wb, ws, "Promodizers");

  // Download
  XLSX.writeFile(wb, "Promodizer_Overview.xlsx");
});
