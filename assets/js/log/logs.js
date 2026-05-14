$(document).ready(function () {
  const table = $("#logsTable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    ordering: false,

    ajax: {
      url: "functions/fetch_logs.php",
      type: "POST",
      data: function (d) {
        d.user = $("#filterUser").val();
        d.reason = $("#filterReason").val();
        d.remarks = $("#filterRemarks").val();
        d.from_date = $("#filterFrom").val();
        d.to_date = $("#filterTo").val();
      },
    },

    order: [[4, "desc"]],

    columnDefs: [
      { width: "14%", targets: 0 }, // User
      { width: "30%", targets: 1 }, // Reason
      { width: "28%", targets: 2 }, // 🔥 Remarks (bigger)
      { width: "20%", targets: 3 }, // Employee
      { width: "8%", targets: 4 }, // Date
    ],
  });

  $("#filterReason").on("change", function () {
    table.draw();
  });

  // TEXT INPUTS (includes clear button + typing + paste)
  $("#filterUser, #filterRemarks").on("input", function () {
    table.draw();
  });

  // 🔥 AUTO RELOAD ON FILTER CHANGE
  $("#filterReason").on("change", function () {
    table.draw();
  });

  $("#filterFrom, #filterTo").on("change", function () {
    table.draw();
  });
});
