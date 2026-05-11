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
  $("#filterName").on("keyup", function () {
    table.column(0).search(this.value).draw();
  });

  // =========================
  // BRANCH FILTER
  // =========================
  $("#filterBranch").on("change", function () {
    var val = this.value;
    table
      .column(1)
      .search(val ? "^" + val + "$" : "", true, false)
      .draw();
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
  $("#filterAssignedBy").on("keyup", function () {
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
