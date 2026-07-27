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

// ─── Employee Report Export (PDF) ─────────────────────────────────────────

function exportEmployeeReport(branchCode, branchLabel) {
  const btn = document.getElementById("btnGenerateEmployee");
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

  const today = new Date();
  const fileSuffix = formatDateFile(today);

  // Quick existence check first so we can show a friendly "no records"
  // message instead of downloading a blank/error PDF.
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

      return fetch(
        "functions/generate_employee_report_pdf.php?" +
          new URLSearchParams({
            branch: branchCode,
            branch_label: branchLabel,
          }),
      )
        .then((res) => {
          if (!res.ok) throw new Error("Failed to generate PDF");
          return res.blob();
        })
        .then((blob) => {
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement("a");
          a.href = url;
          a.download = `${branchCode}_PROMO_LIST_${fileSuffix}.pdf`;
          document.body.appendChild(a);
          a.click();
          a.remove();
          window.URL.revokeObjectURL(url);
        });
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

// ─── Vacant & Incomplete Plantillas Export (PDF) ──────────────────────────

function exportVacantPlantillas(brand, status = "all") {
  const btn = document.getElementById("btnGenerateVacantPlantillas");
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

  const today = new Date();
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
      const hasVacant = status !== "complete" && vacantData.length;
      const hasComplete = status !== "vacant" && completeData.length;

      if (!hasVacant && !hasComplete) {
        Swal.fire({
          icon: "info",
          title: "No records found",
          html: "No plantilla records were found for the selected brand.",
          confirmButtonColor: "#2d68c4",
        });
        return;
      }

      return fetch(
        "functions/generate_vacant_plantillas_pdf.php?" +
          new URLSearchParams({ brand, status }),
      )
        .then((res) => {
          if (!res.ok) throw new Error("Failed to generate PDF");
          return res.blob();
        })
        .then((blob) => {
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement("a");
          a.href = url;
          a.download = `${brand}_${status.toUpperCase()}_PLANTILLAS_${fileSuffix}.pdf`;
          document.body.appendChild(a);
          a.click();
          a.remove();
          window.URL.revokeObjectURL(url);
        });
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

// ─── Branch Plantilla Records Export (PDF) ────────────────────────────────

function exportBranchPlantillas(branch, status = "all") {
  const btn = document.getElementById("btnGenerateBranchPlantillas");
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

  // Use the display label for the header, not the branch code
  const branchLabel =
    document.getElementById("selectBranchPlantillas").selectedOptions[0]
      ?.text ?? branch;

  const today = new Date();
  const fileSuffix = formatDateFile(today);

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
      const hasVacant = status !== "complete" && vacantData.length;
      const hasComplete = status !== "vacant" && completeData.length;

      if (!hasVacant && !hasComplete) {
        Swal.fire({
          icon: "info",
          title: "No records found",
          html: "No plantilla records were found for the selected branch.",
          confirmButtonColor: "#2d68c4",
        });
        return;
      }

      return fetch(
        "functions/generate_branch_plantillas_pdf.php?" +
          new URLSearchParams({ branch, branch_label: branchLabel, status }),
      )
        .then((res) => {
          if (!res.ok) throw new Error("Failed to generate PDF");
          return res.blob();
        })
        .then((blob) => {
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement("a");
          a.href = url;
          a.download = `${branch}_${status.toUpperCase()}_PLANTILLAS_${fileSuffix}.pdf`;
          document.body.appendChild(a);
          a.click();
          a.remove();
          window.URL.revokeObjectURL(url);
        });
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

// ─── Helpers (kept in case used elsewhere in the app) ─────────────────────

function buildFullName(first, middle, last, suffix) {
  return [first, middle, last, suffix]
    .map((p) => (p ?? "").trim())
    .filter(Boolean)
    .join(" ");
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
