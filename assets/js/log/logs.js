$(document).ready(function () {
  const table = $("#logsTable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 10,
    responsive: true,
    dom: "lrtip",

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
      { width: "15%", targets: 0 }, // User
      { width: "20%", targets: 1 }, // Reason
      { width: "35%", targets: 2 }, // 🔥 Remarks (bigger)
      { width: "15%", targets: 3 }, // Employee
      { width: "15%", targets: 4 }, // Date
    ],
  });

  $("#filterReason").on("change", function () {
    table.draw();
  });

  // 🔥 AUTO RELOAD ON FILTER CHANGE
  $("#filterUser, #filterReason, #filterRemarks").on("keyup", function () {
    table.draw();
  });

  $("#filterFrom, #filterTo").on("change", function () {
    table.draw();
  });
});
