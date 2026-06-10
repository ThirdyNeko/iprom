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

  $("#filterRemarksEmpty").on("change", function () {
    const isChecked = $(this).is(":checked");
    const input = $("#filterRemarks");

    if (isChecked) {
      // disable + clear
      input.val("");
      input.prop("disabled", true);
    } else {
      // enable again
      input.prop("disabled", false);
    }

    // reload table
    $("#logsTable").DataTable().draw();
  });
});

document.getElementById("exportExcel").addEventListener("click", function () {
  const filters = {
    user: $("#filterUser").val(),
    reason: $("#filterReason").val(),
    remarks: $("#filterRemarks").val(),
    remarks_empty: $("#filterRemarksEmpty").is(":checked") ? 1 : 0,
    from_date: $("#filterFrom").val(),
    to_date: $("#filterTo").val(),
  };

  fetch("functions/get_logs_export.php?" + new URLSearchParams(filters))
    .then((res) => res.json())
    .then(async (data) => {
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
        ["Updated By", "Reason", "Remarks", "Employee", "Date"],
      ];

      data.forEach((row) => {
        exportData.push([
          row.updated_by,
          row.reason_for_update,
          row.remarks,
          row.employee_name,
          row.update_date,
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
      XLSX.utils.book_append_sheet(wb, ws, "Logs");

      XLSX.writeFile(wb, "Employee_Logs.xlsx");
    });
});
