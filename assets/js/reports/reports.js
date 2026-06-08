let activeReportType = null;

function selectReportType(card) {
  document
    .querySelectorAll(".report-type-card")
    .forEach((c) => c.classList.remove("active"));
  card.classList.add("active");
  activeReportType = card.dataset.type;
}

function generateReport(type) {
  if (type === "complete_plantillas") {
    const brand = document.getElementById("selectBrandComplete").value;
    if (!brand) {
      Swal.fire({
        icon: "warning",
        title: "No brand selected",
        text: "Please select a brand first.",
        confirmButtonColor: "#2d68c4",
      });
      return;
    }
    bootstrap.Modal.getInstance(document.querySelector(".modal.show"))?.hide();
    exportCompletePlantillas(brand);
    // TODO: loadReportTable('complete_plantillas', brand);
  } else if (type === "vacant_plantillas") {
    const brand = document.getElementById("selectBrandVacant").value;
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
    exportVacantPlantillas(brand);
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
      const headerLabel = [`${branchLabel} as of ${dateStr}`, "", "", "", ""];

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

      const exportData = [headerLabel, colHeaders, ...dataRows];

      // ── Build worksheet ───────────────────────────────────────────────
      const ws = XLSX.utils.aoa_to_sheet(exportData);

      // Merge A1:E1 for the header label
      ws["!merges"] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 4 } }];

      // Style A1 — bold, centered (SheetJS CE supports basic styles via cell object)
      if (ws["A1"]) {
        ws["A1"].s = {
          font: { bold: true, sz: 12 },
          alignment: { horizontal: "center", vertical: "center" },
        };
      }

      // Style header row (row 2 = index 1)
      colHeaders.forEach((_, ci) => {
        const cellRef = XLSX.utils.encode_cell({ r: 1, c: ci });
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
      XLSX.writeFile(wb, `${branchCode}_PROMO_INV_${fileSuffix}.xlsx`);
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

function exportVacantPlantillas(brand) {
  const btn = document.getElementById("btnGenerateVacantPlantillas");
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

  const today = new Date();
  const dateStr = formatDateDisplay(today); // e.g. "June 01, 2026"
  const fileSuffix = formatDateFile(today); // e.g. "2026-06-01"

  fetch(
    "functions/get_vacant_plantilla.php?" +
      new URLSearchParams({ brand: brand }),
  )
    .then((res) => res.json())
    .then((data) => {
      if (!data.length) {
        Swal.fire({
          icon: "info",
          title: "No records found",
          html: "No vacant plantillas were found for the selected brand.",
          confirmButtonColor: "#2d68c4",
        });
        return;
      }

      // ── Row 1: Header label (merged visually via empty cols) ──────────
      const headerLabel = [`${brand} as of ${dateStr}`, "", "", "", "", ""];

      // ── Row 2: Column headers ─────────────────────────────────────────
      const colHeaders = [
        "Branch",
        "Plantilla Count",
        "Assigned",
        "Vacancy",
        "Vacant Since",
        "Vacant Period",
      ];

      // ── Data rows ─────────────────────────────────────────────────────
      const dataRows = data.map((p) => [
        p.branch ?? "",
        p.required_count ?? "",
        p.assigned_count ?? "",
        vacantCount(p.required_count, p.assigned_count),
        formatDate(p.timestamp) ?? "",
        monthDaysSince(p.timestamp) ?? "",
      ]);

      const exportData = [headerLabel, colHeaders, ...dataRows];

      // ── Build worksheet ───────────────────────────────────────────────
      const ws = XLSX.utils.aoa_to_sheet(exportData);

      // Merge A1:E1 for the header label
      ws["!merges"] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 5 } }];

      // Style A1 — bold, centered (SheetJS CE supports basic styles via cell object)
      if (ws["A1"]) {
        ws["A1"].s = {
          font: { bold: true, sz: 12 },
          alignment: { horizontal: "center", vertical: "center" },
        };
      }

      // Style header row (row 2 = index 1)
      colHeaders.forEach((_, ci) => {
        const cellRef = XLSX.utils.encode_cell({ r: 1, c: ci });
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

function exportCompletePlantillas(brand) {
  const btn = document.getElementById("btnGenerateCompletePlantillas");
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

  const today = new Date();
  const dateStr = formatDateDisplay(today); // e.g. "June 01, 2026"
  const fileSuffix = formatDateFile(today); // e.g. "2026-06-01"

  fetch(
    "functions/get_complete_plantilla.php?" +
      new URLSearchParams({ brand: brand }),
  )
    .then((res) => res.json())
    .then((data) => {
      if (!data.length) {
        Swal.fire({
          icon: "info",
          title: "No records found",
          html: "No complete plantillas were found for the selected brand.",
          confirmButtonColor: "#2d68c4",
        });
        return;
      }

      // ── Row 1: Header label (merged visually via empty cols) ──────────
      const headerLabel = [`${brand} as of ${dateStr}`, "", "", "", "", ""];

      // ── Row 2: Column headers ─────────────────────────────────────────
      const colHeaders = [
        "Branch",
        "Plantilla Count",
        // "Assigned Count",
        "Complete Since",
        // "Months Complete",
      ];

      // ── Data rows ─────────────────────────────────────────────────────
      const dataRows = data.map((p) => [
        p.branch ?? "",
        p.required_count ?? "",
        // p.assigned_count ?? "",
        formatDate(p.timestamp) ?? "",
        // monthDaysSince(p.timestamp) ?? "",
      ]);

      const exportData = [headerLabel, colHeaders, ...dataRows];

      // ── Build worksheet ───────────────────────────────────────────────
      const ws = XLSX.utils.aoa_to_sheet(exportData);

      // Merge A1:E1 for the header label
      ws["!merges"] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 5 } }];

      // Style A1 — bold, centered (SheetJS CE supports basic styles via cell object)
      if (ws["A1"]) {
        ws["A1"].s = {
          font: { bold: true, sz: 12 },
          alignment: { horizontal: "center", vertical: "center" },
        };
      }

      // Style header row (row 2 = index 1)
      colHeaders.forEach((_, ci) => {
        const cellRef = XLSX.utils.encode_cell({ r: 1, c: ci });
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
      XLSX.utils.book_append_sheet(wb, ws, "Complete Plantillas Report");
      XLSX.writeFile(wb, `${brand}_COMPLETE_PLANTILLAS_${fileSuffix}.xlsx`);
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
