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
        d.remarks_empty = $("#filterRemarksEmpty").is(":checked") ? 1 : 0; // ✅ FIX ADDED
        d.from_date = $("#filterFrom").val();
        d.to_date = $("#filterTo").val();
      },
    },

    order: [[4, "desc"]],

    columnDefs: [
      { width: "14%", targets: 0 }, // User
      { width: "30%", targets: 1 }, // Reason
      { width: "28%", targets: 2 }, // Remarks
      { width: "20%", targets: 3 }, // Employee
      { width: "8%", targets: 4 }, // Date
    ],
  });

  // FILTER EVENTS
  $("#filterReason").on("change", function () {
    table.draw();
  });

  $("#filterUser, #filterRemarks").on("input", function () {
    table.draw();
  });

  $("#filterFrom, #filterTo").on("change", function () {
    table.draw();
  });

  // ✅ CHECKBOX TRIGGER
  $("#filterRemarksEmpty").on("change", function () {
    table.draw();
  });
});
