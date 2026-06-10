let activeReportType = null;

function selectReportType(card) {
  document
    .querySelectorAll(".report-type-card")
    .forEach((c) => c.classList.remove("active"));
  card.classList.add("active");
  activeReportType = card.dataset.type;
}

function generateReport(type) {
  if (type === "vacant_plantillas") {
    const brand = document.getElementById("selectBrandVacant").value;
    const status = document.getElementById("selectStatusVacant").value;
    if (!brand) {
      Swal.fire({
        icon: "warning",
        title: "No brand selected",
        text: "Please select a brand to generate the Vacant & Incomplete Plantillas Report.",
        confirmButtonColor: "#2d68c4",
      });
      return;
    }
    bootstrap.Modal.getInstance(document.querySelector(".modal.show"))?.hide();
    exportVacantPlantillas(brand, status);
  } else if (type === "employee_report") {
    const branchCode = document.getElementById("selectBranch").value;
    const branchLabel =
      document.getElementById("selectBranch").selectedOptions[0]?.text ??
      branchCode;
    if (!branchCode) {
      Swal.fire({
        icon: "warning",
        title: "No branch selected",
        text: "Please select a branch first.",
        confirmButtonColor: "#2d68c4",
      });
      return;
    }
    bootstrap.Modal.getInstance(document.querySelector(".modal.show"))?.hide();
    exportEmployeeReport(branchCode, branchLabel);
    // in generateReport():
  } else if (type === "branch_plantillas") {
    const branch = document.getElementById("selectBranchPlantillas").value;
    const status = document.getElementById(
      "selectStatusBranchPlantillas",
    ).value;
    if (!branch) {
      Swal.fire({
        icon: "warning",
        title: "No branch selected",
        text: "Please select a branch to generate the Branch Plantilla Records.",
        confirmButtonColor: "#2d68c4",
      });
      return;
    }
    bootstrap.Modal.getInstance(document.querySelector(".modal.show"))?.hide();
    exportBranchPlantillas(branch, status);
  }
}

// ─── Employee Report Export ───────────────────────────────────────────────────

function exportEmployeeReport(branchCode, branchLabel) {
  const btn = document.getElementById("btnGenerateEmployee");
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

  const today = new Date();
  const dateStr = formatDateDisplay(today); // e.g. "June 01, 2026"
  const fileSuffix = formatDateFile(today); // e.g. "2026-06-01"

  fetch(
    "functions/get_employee_report.php?" +
      new URLSearchParams({ branch: branchCode }),
  )
    .then((res) => res.json())
    .then((data) => {
      if (!data.length) {
        Swal.fire({
          icon: "info",
          title: "No records found",
          html: "No employees were found for the selected branch.",
          confirmButtonColor: "#2d68c4",
        });
        return;
      }

      // ── Row 1: Header label (merged visually via empty cols) ──────────
      const headerLabel = [
        [`${branchLabel}`, "", "", "", ""],
        [`As of ${dateStr}`, "", "", "", ""],
      ];

      // ── Row 2: Column headers ─────────────────────────────────────────
      const colHeaders = [
        "Brand",
        "First Name",
        "Last Name",
        "Middle Name",
        "Suffix",
        "Employment Status",
        "Sub-Status",
        "Date Hired",
      ];

      // ── Data rows ─────────────────────────────────────────────────────
      const dataRows = data.map((p) => [
        p.brand ?? "",
        p.first_name ?? "",
        p.last_name ?? "",
        p.middle_name ?? "",
        p.suffix ?? "",
        p.employment_status ?? "",
        p.sub_status ?? "",
        formatDate(p.date_hired),
      ]);

      const exportData = [...headerLabel, colHeaders, ...dataRows];

      // ── Build worksheet ───────────────────────────────────────────────
      const ws = XLSX.utils.aoa_to_sheet(exportData);

      // Merge A1:E1 for the header label
      ws["!merges"] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: 4 } },
        { s: { r: 1, c: 0 }, e: { r: 1, c: 4 } }, // ← add this
      ];

      // Style A1 — bold, centered (SheetJS CE supports basic styles via cell object)
      if (ws["A1"]) {
        ws["A1"].s = {
          font: { bold: true, sz: 12 },
          alignment: { horizontal: "center", vertical: "center" },
        };
      }

      if (ws["A2"]) {
        ws["A2"].s = {
          font: { sz: 10, italic: true },
          alignment: { horizontal: "center", vertical: "center" },
        };
      }

      // Style header row (row 2 = index 1)
      colHeaders.forEach((_, ci) => {
        const cellRef = XLSX.utils.encode_cell({ r: 2, c: ci });
        if (ws[cellRef]) {
          ws[cellRef].s = {
            font: { bold: true, color: { rgb: "FFFFFF" } },
            fill: { fgColor: { rgb: "2D68C4" } },
            alignment: { horizontal: "center" },
          };
        }
      });

      // Auto column widths (based on all rows including header label)
      ws["!cols"] = colHeaders.map((_, ci) => {
        let max = 10;
        exportData.forEach((row) => {
          const val = row[ci] ? row[ci].toString() : "";
          max = Math.max(max, val.length);
        });
        return { wch: max + 2 };
      });

      // ── Write file ────────────────────────────────────────────────────
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, "Employee Report");
      XLSX.writeFile(wb, `${branchCode}_PROMO_LIST_${fileSuffix}.xlsx`);
    })
    .catch(() => {
      Swal.fire({
        icon: "error",
        title: "Export failed",
        text: "Something went wrong while fetching the data.",
        confirmButtonColor: "#2d68c4",
      });
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = "Generate Report";
    });
}

function exportVacantPlantillas(brand, status = "all") {
  const btn = document.getElementById("btnGenerateVacantPlantillas");
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

  const today = new Date();
  const dateStr = formatDateDisplay(today);
  const fileSuffix = formatDateFile(today);

  Promise.all([
    fetch(
      "functions/get_vacant_plantilla.php?" + new URLSearchParams({ brand }),
    ).then((r) => r.json()),
    fetch(
      "functions/get_complete_plantilla.php?" + new URLSearchParams({ brand }),
    ).then((r) => r.json()),
  ])
    .then(([vacantData, completeData]) => {
      const vacantRows = vacantData.map((p) => [
        p.brand ?? "",
        p.branch ?? "",
        p.required_count ?? "",
        p.assigned_count ?? "",
        vacantCount(p.required_count, p.assigned_count),
        formatDate(p.timestamp) ?? "",
        monthDaysSince(p.timestamp) ?? "",
        "",
        "",
      ]);

      const completeRows = completeData.map((p) => [
        p.brand ?? "",
        p.branch ?? "",
        p.required_count ?? "",
        p.assigned_count ?? "",
        0, // vacant
        "", // vacant since
        "", // vacant period
        formatDate(p.timestamp) ?? "",
        monthDaysSince(p.timestamp) ?? "",
      ]);

      const combined = [
        ...(status === "complete" ? [] : vacantRows),
        ...(status === "vacant" ? [] : completeRows),
      ].sort((a, b) => a[0].localeCompare(b[0]) || a[1].localeCompare(b[1]));
      //                 ^ brand (col 0)              ^ branch (col 1)

      if (!combined.length) {
        Swal.fire({
          icon: "info",
          title: "No records found",
          html: "No plantilla records were found for the selected brand.",
          confirmButtonColor: "#2d68c4",
        });
        return;
      }

      const headerLabel = [
        [`${brand}`, "", "", "", "", "", "", "", ""],
        [`As of ${dateStr}`, "", "", "", "", "", "", "", ""],
      ];

      const colHeaders = [
        "Brand",
        "Branch",
        "Plantilla",
        "Deployed",
        "Vacant",
        "Vacant Since",
        "Vacant Period",
        "Complete Since",
        "Complete Period",
      ];

      const exportData = [...headerLabel, colHeaders, ...combined];

      const ws = XLSX.utils.aoa_to_sheet(exportData);

      ws["!merges"] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: 8 } },
        { s: { r: 1, c: 0 }, e: { r: 1, c: 8 } },
      ];

      if (ws["A1"]) {
        ws["A1"].s = {
          font: { bold: true, sz: 12 },
          alignment: { horizontal: "center", vertical: "center" },
        };
      }

      if (ws["A2"]) {
        ws["A2"].s = {
          font: { sz: 10, italic: true },
          alignment: { horizontal: "center", vertical: "center" },
        };
      }

      colHeaders.forEach((_, ci) => {
        const cellRef = XLSX.utils.encode_cell({ r: 2, c: ci });
        if (ws[cellRef]) {
          ws[cellRef].s = {
            font: { bold: true, color: { rgb: "FFFFFF" } },
            fill: { fgColor: { rgb: "2D68C4" } },
            alignment: { horizontal: "center" },
          };
        }
      });

      ws["!cols"] = colHeaders.map((_, ci) => {
        let max = 10;
        exportData.forEach((row) => {
          const val = row[ci] ? row[ci].toString() : "";
          max = Math.max(max, val.length);
        });
        return { wch: max + 2 };
      });

      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, "Vacant Plantillas Report");
      XLSX.writeFile(wb, `${brand}_VACANT_PLANTILLAS_${fileSuffix}.xlsx`);
    })
    .catch(() => {
      Swal.fire({
        icon: "error",
        title: "Export failed",
        text: "Something went wrong while fetching the data.",
        confirmButtonColor: "#2d68c4",
      });
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = "Generate Report";
    });
}

function exportBranchPlantillas(branch, status = "all") {
  const btn = document.getElementById("btnGenerateBranchPlantillas");
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

  const today = new Date();
  const dateStr = formatDateDisplay(today);
  const fileSuffix = formatDateFile(today);

  // Use the display label for the header, not the branch code
  const branchLabel =
    document.getElementById("selectBranchPlantillas").selectedOptions[0]
      ?.text ?? branch;

  Promise.all([
    fetch(
      "functions/get_vacant_plantilla_branch.php?" +
        new URLSearchParams({ branch }),
    ).then((r) => r.json()),
    fetch(
      "functions/get_complete_plantilla_branch.php?" +
        new URLSearchParams({ branch }),
    ).then((r) => r.json()),
  ])
    .then(([vacantData, completeData]) => {
      const vacantRows = vacantData.map((p) => [
        p.branch ?? "",
        p.brand ?? "",
        p.required_count ?? "",
        p.assigned_count ?? "",
        vacantCount(p.required_count, p.assigned_count),
        formatDate(p.timestamp) ?? "",
        monthDaysSince(p.timestamp) ?? "",
        "",
        "",
      ]);

      const completeRows = completeData.map((p) => [
        p.branch ?? "",
        p.brand ?? "",
        p.required_count ?? "",
        p.assigned_count ?? "",
        0,
        "",
        "",
        formatDate(p.timestamp) ?? "",
        monthDaysSince(p.timestamp) ?? "",
      ]);

      const combined = [
        ...(status === "complete" ? [] : vacantRows),
        ...(status === "vacant" ? [] : completeRows),
      ].sort((a, b) => a[0].localeCompare(b[0]) || a[1].localeCompare(b[1]));

      if (!combined.length) {
        Swal.fire({
          icon: "info",
          title: "No records found",
          html: "No plantilla records were found for the selected branch.",
          confirmButtonColor: "#2d68c4",
        });
        return;
      }

      const headerLabel = [
        [`${branchLabel}`, "", "", "", "", "", "", "", ""],
        [`As of ${dateStr}`, "", "", "", "", "", "", "", ""],
      ];

      const colHeaders = [
        "Branch",
        "Brand",
        "Plantilla",
        "Deployed",
        "Vacant",
        "Vacant Since",
        "Vacant Period",
        "Complete Since",
        "Complete Period",
      ];

      const exportData = [...headerLabel, colHeaders, ...combined];

      const ws = XLSX.utils.aoa_to_sheet(exportData);

      ws["!merges"] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: 8 } },
        { s: { r: 1, c: 0 }, e: { r: 1, c: 8 } },
      ];

      if (ws["A1"]) {
        ws["A1"].s = {
          font: { bold: true, sz: 12 },
          alignment: { horizontal: "center", vertical: "center" },
        };
      }

      if (ws["A2"]) {
        ws["A2"].s = {
          font: { sz: 10, italic: true },
          alignment: { horizontal: "center", vertical: "center" },
        };
      }

      colHeaders.forEach((_, ci) => {
        const cellRef = XLSX.utils.encode_cell({ r: 2, c: ci });
        if (ws[cellRef]) {
          ws[cellRef].s = {
            font: { bold: true, color: { rgb: "FFFFFF" } },
            fill: { fgColor: { rgb: "2D68C4" } },
            alignment: { horizontal: "center" },
          };
        }
      });

      ws["!cols"] = colHeaders.map((_, ci) => {
        let max = 10;
        exportData.forEach((row) => {
          const val = row[ci] ? row[ci].toString() : "";
          max = Math.max(max, val.length);
        });
        return { wch: max + 2 };
      });

      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, "Branch Plantilla Report");
      XLSX.writeFile(wb, `${branch}_PLANTILLAS_${fileSuffix}.xlsx`);
    })
    .catch(() => {
      Swal.fire({
        icon: "error",
        title: "Export failed",
        text: "Something went wrong while fetching the data.",
        confirmButtonColor: "#2d68c4",
      });
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = "Generate Report";
    });
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function buildFullName(first, middle, last, suffix) {
  return [first, middle, last, suffix]
    .map((p) => (p ?? "").trim())
    .filter(Boolean)
    .join(" ");
}

function vacantCount(required, assigned) {
  return required - assigned;
}

function monthDaysSince(timestamp) {
  if (!timestamp) return "";
  const then = new Date(timestamp);
  const now = new Date();

  let months =
    (now.getFullYear() - then.getFullYear()) * 12 +
    (now.getMonth() - then.getMonth());
  let days = now.getDate() - then.getDate();

  if (days < 0) {
    months--;
    const prevMonth = new Date(now.getFullYear(), now.getMonth(), 0);
    days += prevMonth.getDate();
  }

  if (months === 0) return `${days}d`;
  if (days === 0) return `${months}mo`;
  return `${months}mo ${days}d`;
}

function formatDate(value) {
  if (!value) return "";
  const d = new Date(value);
  if (isNaN(d)) return value;
  return d.toLocaleDateString("en-US", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
}

function formatDateDisplay(d) {
  return d.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "2-digit",
  });
}

function formatDateFile(d) {
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  const yyyy = d.getFullYear();
  return `${mm}-${dd}-${yyyy}`;
}
