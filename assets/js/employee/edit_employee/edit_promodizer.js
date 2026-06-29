const modalEl = document.getElementById("editPromodizerModal");
let branchBrandPairs = [];

function getCorpoForBranch(branchCode) {
  const pair = branchBrandPairs.find((p) => p.branch_code === branchCode);
  return pair?.corpo || "";
}

function setCorpoFromBranch(branchCode) {
  const corpoInput = document.getElementById("editCorpo");
  if (!corpoInput) return;
  corpoInput.value = getCorpoForBranch(branchCode).toUpperCase();
  autoResizeInput(corpoInput);
}

function cleanValue(value) {
  if (!value) return "";
  const trimmed = value.toString().trim();
  return trimmed.toLowerCase() === "null" || trimmed === "" ? "" : trimmed;
}

function autoResizeInput(input) {
  if (!input) return;
  const minSize = 10;
  input.size = Math.max(input.value.length, minSize);
}

function autoResizeSelectText(select) {
  if (!select) return;
  const textLength = select.options[select.selectedIndex]?.text.length || 0;
  if (textLength > 35) {
    select.style.fontSize = "11px";
  } else if (textLength > 25) {
    select.style.fontSize = "12px";
  } else {
    select.style.fontSize = "14px";
  }
}

function checkPrintBtnState() {
  const status =
    document.getElementById("editStatus")?.value?.trim()?.toUpperCase() || "";

  const printBtn = document.getElementById("openPrintModalBtn");
  if (!printBtn) return;

  const rawCanPrintLOA = window.canPrintLOA;
  const isAdmin = ["admin", "super_admin"].includes(window.userRole || "");
  const canPrintLOA = Number(rawCanPrintLOA || 0) === 1;

  console.log("canPrintLOA raw value:", rawCanPrintLOA);
  console.log("canPrintLOA normalized:", canPrintLOA);
  console.log("userRole:", window.userRole);

  const allowed = isAdmin || canPrintLOA;
  printBtn.disabled = !allowed || status === "INACTIVE";
}

document.addEventListener("DOMContentLoaded", function () {
  const editModal = document.getElementById("editPromodizerModal");

  editModal.addEventListener("show.bs.modal", function () {
    const editStatus = document.getElementById("editStatus");
    checkPrintBtnState();

    if (!editStatus._bound) {
      editStatus.addEventListener("change", checkPrintBtnState);
      editStatus.addEventListener("input", checkPrintBtnState);

      const observer = new MutationObserver(checkPrintBtnState);
      observer.observe(editStatus, {
        attributes: true,
        childList: true,
        subtree: true,
      });

      editStatus._bound = true;
    }
  });
});

// =========================
// ELEMENT REFERENCES
// =========================
const reasonSelect = document.getElementById("editReasonUpdate");
const dateSeparatedRow = document.getElementById("rowDateSeparated");
const dateSeparatedInput = document.getElementById("editDateSeparated");
const dateReturnedRow = document.getElementById("rowDateReturned");
const dateReturnedInput = document.getElementById("editDateReturn");
const employmentStatusSelect = document.getElementById("editEmploymentStatus");
const editAgency = document.getElementById("editAgency");
const startDateRow = document.getElementById("rowStartDate");
const startDateInput = document.getElementById("editStartDate");
const endDateRow = document.getElementById("rowEndDate");
const endDateInput = document.getElementById("editEndDate");
const editRovingField = document.getElementById("editRovingField");
const editRovingContainer = document.getElementById("editRovingContainer");
const editMultiBrandField = document.getElementById("editMultiBrandField");
const editMultiBrandContainer = document.getElementById(
  "editMultiBrandContainer",
);
const editGender = document.getElementById("editGender");
const editBirthday = document.getElementById("editBirthday");
const editDateHired = document.getElementById("editDateHired");

function toggleEmploymentDates() {
  if (!employmentStatusSelect || !reasonSelect) return;

  const status = (employmentStatusSelect.value || "").trim().toUpperCase();
  const reason = (reasonSelect.value || "").trim().toUpperCase();

  const isRelieverOrSeasonal = status === "RELIEVER" || status === "SEASONAL";
  const isPermanent = status === "PERMANENT";
  const isCorrectReason = reason === "CHANGE EMPLOYMENT STATUS";

  const shouldShowStart = isRelieverOrSeasonal || isPermanent;
  const shouldShowEnd = isRelieverOrSeasonal;

  if (startDateRow) startDateRow.style.display = shouldShowStart ? "" : "none";
  if (endDateRow) endDateRow.style.display = shouldShowEnd ? "" : "none";

  const shouldEnableStart =
    (isRelieverOrSeasonal || isPermanent) && isCorrectReason;
  if (startDateInput) {
    startDateInput.disabled = !shouldEnableStart;
    startDateInput.required = shouldEnableStart;
    if (!shouldShowStart) startDateInput.value = "";
  }

  const shouldEnableEnd = isRelieverOrSeasonal && isCorrectReason;
  if (endDateInput) {
    endDateInput.disabled = !shouldEnableEnd;
    endDateInput.required = shouldEnableEnd;
    if (isPermanent) {
      endDateInput.disabled = true;
      endDateInput.required = false;
    }
    if (!shouldShowEnd) endDateInput.value = "";
  }
}

function toggleBranchReasonOptions() {
  if (!reasonSelect) return;

  const subStatus = (
    document.getElementById("editSubStatus")?.value || ""
  ).toUpperCase();
  const status = (
    document.getElementById("editStatus")?.value || ""
  ).toUpperCase();

  const isStationary = subStatus === "STATIONARY";
  const isInactive = status === "INACTIVE";

  const addOpt = reasonSelect.querySelector('option[value="ADD BRANCH/BRAND"]');
  const removeOpt = reasonSelect.querySelector(
    'option[value="REMOVE BRANCH/BRAND"]',
  );

  if (addOpt) addOpt.disabled = isStationary;
  if (removeOpt) removeOpt.disabled = isStationary;

  const inactiveOnly = new Set([
    "BLACKLISTED / AWOL / TERMINATED",
    "PULL-OUT / END OF CONTRACT",
    "RESIGNED",
  ]);

  reasonSelect.querySelectorAll("option").forEach((opt) => {
    if (opt.value === "") return;
    if (isInactive) {
      const belongs = inactiveOnly.has(opt.value);
      opt.disabled = !belongs;
      opt.style.color = !belongs ? "#aaa" : "";
    } else {
      opt.style.color = "";
    }
  });

  const selected = reasonSelect.querySelector(
    `option[value="${CSS.escape(reasonSelect.value)}"]`,
  );
  if (selected?.disabled) reasonSelect.value = "";
}

function toggleReasonDates() {
  if (!reasonSelect) return;

  const reason = (reasonSelect.value || "").trim().toUpperCase();
  const value = (employmentStatusSelect.value || "").trim().toUpperCase();

  const shouldShow = value === "RELIEVER" || value === "SEASONAL";

  const isTerminationReason =
    reason === "RESIGNED" ||
    reason === "PULL-OUT / END OF CONTRACT" ||
    reason === "BLACKLISTED / AWOL / TERMINATED" ||
    reason === "REMOVE BRANCH/BRAND";

  const shouldShowReason =
    !isTerminationReason &&
    (reason === "TRANSFER BRANCH" ||
      reason === "CHANGE SUB STATUS" ||
      reason === "REASSIGN" ||
      reason === "ADD BRANCH/BRAND" ||
      reason === "CHANGE EMPLOYMENT STATUS");

  if (shouldShowReason && startDateInput.value) startDateInput.value = "";

  const showStart = shouldShowReason || shouldShow;
  if (startDateRow) startDateRow.style.display = showStart ? "" : "none";
  if (startDateInput) {
    const disableStart = !showStart || isTerminationReason;
    startDateInput.disabled = disableStart;
    startDateInput.required = !disableStart;
  }

  const showEnd = shouldShow;
  if (endDateRow) endDateRow.style.display = showEnd ? "" : "none";
  if (endDateInput) {
    const disableEnd = !showEnd || isTerminationReason;
    endDateInput.disabled = disableEnd;
    endDateInput.required = !disableEnd;
    if (!showEnd) endDateInput.value = "";
  }
}

if (!reasonSelect || !dateSeparatedInput || !dateReturnedInput) {
  console.error("Modal elements not found. Check modal HTML.");
}

const showDateSeparatedReasons = [
  "RESIGNED",
  "PULL-OUT / END OF CONTRACT",
  "BLACKLISTED / AWOL / TERMINATED",
  "MATERNITY LEAVE",
  "EMERGENCY LEAVE",
  "REMOVE BRANCH/BRAND",
];

function toggleDateSeparated() {
  if (!reasonSelect) return;
  const value = (reasonSelect.value || "").trim().toUpperCase();
  const shouldShow = showDateSeparatedReasons.includes(value);

  if (dateSeparatedRow)
    dateSeparatedRow.style.display = shouldShow ? "" : "none";
  if (dateSeparatedInput) {
    dateSeparatedInput.disabled = !shouldShow;
    dateSeparatedInput.required = shouldShow;
    if (!shouldShow) dateSeparatedInput.value = "";
  }
}

function toggleAddButtons() {
  const selectedReason = cleanValue(reasonSelect?.value).toUpperCase();
  const canAdd =
    selectedReason === "ADD BRANCH/BRAND" ||
    selectedReason === "CHANGE SUB STATUS";

  document.querySelectorAll(".btn-add-branch").forEach((btn) => {
    btn.style.display = canAdd ? "" : "none";
  });
  document.querySelectorAll(".btn-add-brand").forEach((btn) => {
    btn.style.display = canAdd ? "" : "none";
  });
}

function toggleStatusesEditable() {
  if (!reasonSelect) return;
  const reason = (reasonSelect.value || "").toUpperCase();

  const subStatusEl = document.getElementById("editSubStatus");
  const employmentStatusEl = document.getElementById("editEmploymentStatus");
  const agencyEl = document.getElementById("editAgency");

  if (subStatusEl) subStatusEl.disabled = true;
  if (employmentStatusEl) employmentStatusEl.disabled = true;
  if (agencyEl) agencyEl.disabled = true;

  switch (reason) {
    case "CHANGE SUB STATUS":
      if (subStatusEl) subStatusEl.disabled = false;
      break;
    case "CHANGE EMPLOYMENT STATUS":
      if (employmentStatusEl) employmentStatusEl.disabled = false;
      break;
    case "CHANGE AGENCY":
      if (agencyEl) agencyEl.disabled = false;
      break;
  }
}

function toggleTransferEditable() {
  if (!reasonSelect) return;
  const reason = (reasonSelect.value || "").toUpperCase();

  const subStatusEl = document.getElementById("editSubStatus");
  const employmentStatusEl = document.getElementById("editEmploymentStatus");
  const subStatus = (subStatusEl?.value || "").toUpperCase();
  const employmentStatus = (employmentStatusEl?.value || "").toUpperCase();

  const branchEl = document.getElementById("editBranch");
  const brandEl = document.getElementById("editBrand");

  if (branchEl) branchEl.disabled = true;
  if (brandEl) brandEl.disabled = true;

  if (reason !== "TRANSFER BRANCH" && reason !== "REASSIGN") return;

  if (subStatus === "STATIONARY" && employmentStatus !== "RELIEVER") {
    if (branchEl) branchEl.disabled = false;
    if (reason === "REASSIGN") {
      if (brandEl) brandEl.disabled = false;
    }
  }
}

function safeArray(value) {
  if (!value) return [];
  if (Array.isArray(value)) return value;
  if (typeof value === "string") {
    try {
      const parsed = JSON.parse(value);
      if (Array.isArray(parsed)) return parsed;
    } catch (e) {}
    return value
      .split(",")
      .map((v) => v.trim())
      .filter(Boolean);
  }
  return [];
}

function syncMultiUI(status) {
  const value = (status || "").toUpperCase();

  if (editRovingField) editRovingField.classList.add("d-none");
  if (editMultiBrandField) editMultiBrandField.classList.add("d-none");

  if (value === "MULTI BRANCH" || value === "HYBRID") {
    if (editRovingField) editRovingField.classList.remove("d-none");
  }
  if (value === "MULTI BRAND" || value === "HYBRID") {
    if (editMultiBrandField) editMultiBrandField.classList.remove("d-none");
  }
}

function updateDateStyle(input) {
  if (!input) return;
  if (input.value) {
    input.classList.add("has-value");
  } else {
    input.classList.remove("has-value");
  }
}

function toggleSubStatusOptions() {
  const editSubStatus = document.getElementById("editSubStatus");
  if (!editSubStatus) return;

  const value = (editSubStatus.value || "").toUpperCase();
  const multiBranchOpt = editSubStatus.querySelector(
    'option[value="MULTI BRANCH"]',
  );
  const multiBrandOpt = editSubStatus.querySelector(
    'option[value="MULTI BRAND"]',
  );

  if (multiBranchOpt) multiBranchOpt.disabled = false;
  if (multiBrandOpt) multiBrandOpt.disabled = false;

  if (value === "MULTI BRAND" && multiBranchOpt) multiBranchOpt.disabled = true;
  if (value === "MULTI BRANCH" && multiBrandOpt) multiBrandOpt.disabled = true;
}

function toggleDateReturned() {
  if (!reasonSelect) return;
  const value = (reasonSelect.value || "").trim().toUpperCase();
  const shouldShow = value === "MATERNITY LEAVE" || value === "EMERGENCY LEAVE";

  if (dateReturnedRow) dateReturnedRow.style.display = shouldShow ? "" : "none";
  if (dateReturnedInput) {
    dateReturnedInput.disabled = !shouldShow;
    dateReturnedInput.required = shouldShow;
    if (!shouldShow) dateReturnedInput.value = "";
  }
}

function populateEditBranch(
  branches = [],
  currentBrand = null,
  baseBranch = null,
  mode = "STATIONARY",
) {
  const branchSelect = document.getElementById("editBranch");
  if (!branchSelect) return;

  const list = safeArray(branches);
  const validBranches = branchBrandPairs.filter(
    (p) => p.brand_name === currentBrand,
  );
  const uniqueBranches = [...new Set(validBranches.map((p) => p.branch_code))];

  branchSelect.innerHTML = uniqueBranches
    .map((code) => {
      const pair = branchBrandPairs.find(
        (p) => p.branch_code === code && p.brand_name === currentBrand,
      );
      const displayName = pair?.branch_name || code;
      return `<option value="${code}" ${list.includes(code) ? "selected" : ""}>${displayName}</option>`;
    })
    .join("");
}

function populateEditBrand(
  brands = [],
  currentBranch = null,
  baseBrand = null,
  mode = "STATIONARY",
) {
  const brandSelect = document.getElementById("editBrand");
  if (!brandSelect) return;

  const list = safeArray(brands);
  const validBrands = branchBrandPairs.filter(
    (p) => p.branch_code === currentBranch,
  );
  const uniqueBrands = [...new Set(validBrands.map((p) => p.brand_name))];

  let selectedBrand =
    baseBrand || list.find((b) => uniqueBrands.includes(b)) || "";

  brandSelect.innerHTML =
    `<option value="">Select brand</option>` +
    uniqueBrands
      .map(
        (b) =>
          `<option value="${b}" ${b === selectedBrand ? "selected" : ""}>${b}</option>`,
      )
      .join("");

  brandSelect.value = selectedBrand;
}

function populateEditRoving(
  branches = [],
  currentBrand = null,
  baseBranch = null,
) {
  if (!editRovingContainer) return;

  let list = [...new Set(safeArray(branches))];

  const selectedReason = cleanValue(reasonSelect?.value).toUpperCase();
  const canAddBranch =
    selectedReason === "ADD BRANCH/BRAND" ||
    selectedReason === "CHANGE SUB STATUS";

  const validBranches = branchBrandPairs
    .filter(
      (p) =>
        p.brand_name === currentBrand &&
        p.branch_code !== baseBranch &&
        (p.assigned_count < p.required_count || list.includes(p.branch_code)),
    )
    .map((p) => p.branch_code);

  const uniqueBranches = [...new Set(validBranches)];
  const finalAvailable = uniqueBranches.filter((b) => !list.includes(b));

  if (list.length === 0 && finalAvailable.length === 0) {
    editRovingContainer.innerHTML = "";
    return;
  }
  if (list.length === 0 && finalAvailable.length > 0) {
    list = [""];
  }

  list = list.filter((b, i) => b !== "" || i === 0);

  editRovingContainer.innerHTML = list
    .map((b, index) => {
      const isExisting = b !== "";
      if (isExisting && !uniqueBranches.includes(b)) return "";
      return `
        <div class="d-flex gap-2 mb-2 align-items-center roving-row">
          <select class="form-control" ${isExisting ? "disabled" : ""}>
            <option value="">Select branch</option>
            ${uniqueBranches
              .map((branchCode) => {
                const displayName =
                  branchBrandPairs.find(
                    (p) =>
                      p.branch_code === branchCode &&
                      p.brand_name === currentBrand,
                  )?.branch_name || branchCode;
                return `<option value="${branchCode}" ${branchCode === b ? "selected" : ""}>${displayName}</option>`;
              })
              .join("")}
          </select>
          <button type="button" class="btn btn-success btn-add-branch" style="${canAddBranch ? "" : "display:none;"}">+</button>
          <button type="button" class="btn btn-danger btn-remove-branch" style="${isExisting || index === 0 ? "display:none;" : ""}">−</button>
        </div>`;
    })
    .join("");
}

function populateEditBrands(
  brands = [],
  currentBranch = null,
  baseBrand = null,
) {
  if (!editMultiBrandContainer) return;

  let list = [...new Set(safeArray(brands))];

  const selectedReason = cleanValue(reasonSelect?.value).toUpperCase();
  const canAddBrand =
    selectedReason === "ADD BRANCH/BRAND" ||
    selectedReason === "CHANGE SUB STATUS";

  const validBrands = branchBrandPairs
    .filter(
      (p) =>
        p.branch_code === currentBranch &&
        p.brand_name !== baseBrand &&
        (p.assigned_count < p.required_count || list.includes(p.brand_name)),
    )
    .map((p) => p.brand_name);

  const uniqueBrands = [...new Set(validBrands)];
  const finalAvailable = uniqueBrands.filter((b) => !list.includes(b));

  if (list.length === 0 && finalAvailable.length === 0) {
    editMultiBrandContainer.innerHTML = "";
    return;
  }
  if (list.length === 0 && finalAvailable.length > 0) {
    list = [""];
  }

  list = list.filter((b, i) => b !== "" || i === 0);

  editMultiBrandContainer.innerHTML = list
    .map((b, index) => {
      const isExisting = b !== "";
      if (isExisting && !uniqueBrands.includes(b)) return "";
      return `
        <div class="d-flex gap-2 mb-2 align-items-center brand-row">
          <select class="form-control" ${isExisting ? "disabled" : ""}>
            <option value="">Select brand</option>
            ${uniqueBrands
              .map(
                (brand) =>
                  `<option value="${brand}" ${brand === b ? "selected" : ""}>${brand}</option>`,
              )
              .join("")}
          </select>
          <button type="button" class="btn btn-success btn-add-brand" style="${canAddBrand ? "" : "display:none;"}">+</button>
          <button type="button" class="btn btn-danger btn-remove-brand" style="${isExisting || index === 0 ? "display:none;" : ""}">−</button>
        </div>`;
    })
    .join("");
}

function collectAssignments() {
  const branches = Array.from(
    document.querySelectorAll("#editRovingSelect option:checked"),
  ).map((opt) => opt.value);
  const brands = Array.from(
    document.querySelectorAll("#editBrandSelect option:checked"),
  ).map((opt) => opt.value);
  return { branches, removedBranches: [], brands, removedBrands: [] };
}

function resetEditModal() {
  editRovingContainer.innerHTML = "";
  editMultiBrandContainer.innerHTML = "";

  const protectedSelects = [
    "editReasonUpdate",
    "editEmploymentStatus",
    "editSubStatus",
  ];

  modalEl.querySelectorAll("select").forEach((s) => {
    if (!protectedSelects.includes(s.id)) {
      s.value = "";
      s.disabled = false;
    }
  });

  if (editAgency) {
    editAgency.value = "";
    editAgency.disabled = true;
  }
}

// =========================
// OPEN EMPLOYEE MODAL
// Extracted into a named function so promodizers.js can call it
// directly for the ?edit=ID URL flow.
// =========================
async function openEmployeeModal(id) {
  await initPromise;
  resetEditModal();

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  try {
    const res = await fetch(`functions/get_employee.php?id=${id}`);
    const p = await res.json();

    if (!p || !p.id) {
      Swal.fire({ icon: "error", title: "Error", text: "Employee not found" });
      return;
    }

    const employee = {
      id: p.id,
      employee_id: p.employee_id,
      first_name: p.first_name,
      middle_name: p.middle_name,
      last_name: p.last_name,
      suffix: p.suffix,
      branch: p.branch,
      corpo: p.corpo,
      brand: p.brand,
      agency: p.agency,
      assignment_date: p.assignment_date,
      last_assigned_by: p.last_assigned_by,
      status: p.status,
      date_of_return: p.date_of_return,
      date_separated: p.date_separated,
      employment_status: p.employment_status,
      sub_status: p.sub_status,
      remarks: p.remarks,
      last_updated_by: p.last_updated_by,
      reason_update: p.reason_for_update,
      date_hired: p.date_hired,
      updated_at: p.updated_at,
      start_date: p.start_date,
      end_date: p.end_date,
      gender: p.gender,
      birthday: p.birthday,
    };

    window.currentEmployee = employee;
    window.canPrintLOA = p.print_loa ?? 0;

    const el = (id) => document.getElementById(id);

    if (el("editPromodizerId")) el("editPromodizerId").value = employee.id;
    if (el("editEmployeeId")) el("editEmployeeId").value = employee.employee_id;
    if (el("editFirstName"))
      el("editFirstName").value = cleanValue(employee.first_name);
    if (el("editLastName"))
      el("editLastName").value = cleanValue(employee.last_name);
    if (el("editMiddleName"))
      el("editMiddleName").value = cleanValue(employee.middle_name);
    if (el("editSuffix")) el("editSuffix").value = cleanValue(employee.suffix);

    if (el("editBranch")) {
      populateEditBranch([employee.branch], employee.brand, employee.branch);
      console.log("branchBrandPairs", branchBrandPairs.length);
      console.log("employee.branch", employee.branch);
      console.log("employee.brand", employee.brand);
    }
    if (el("editCorpo")) setCorpoFromBranch(employee.branch);
    if (el("editBrand"))
      populateEditBrand([employee.brand], employee.branch, employee.brand);
    if (editAgency) populateAgencyDropdown(employee.agency);

    if (el("editDateHired"))
      el("editDateHired").value = employee.date_hired || "";
    if (el("editGender"))
      el("editGender").value = cleanValue(employee.gender) || "";
    if (el("editBirthday")) el("editBirthday").value = employee.birthday || "";
    if (el("editStatus"))
      el("editStatus").value = cleanValue(employee.status) || "-";
    if (el("editLastAssignedBy"))
      el("editLastAssignedBy").value = cleanValue(employee.last_assigned_by);

    if (el("editAssignmentDate")) {
      const d = employee.assignment_date;
      if (d) {
        const dateObj = new Date(d);
        const mm = String(dateObj.getMonth() + 1).padStart(2, "0");
        const dd = String(dateObj.getDate()).padStart(2, "0");
        const yyyy = dateObj.getFullYear();
        el("editAssignmentDate").value = `${mm}/${dd}/${yyyy}`;
      } else {
        el("editAssignmentDate").value = "";
      }
    }

    if (el("editStartDate"))
      el("editStartDate").value = employee.start_date || "";
    if (el("editEndDate"))
      el("editEndDate").value = cleanValue(employee.end_date);

    const employmentSelect = el("editEmploymentStatus");
    if (employmentSelect) {
      const empStatus = cleanValue(employee.employment_status).toUpperCase();
      employmentSelect.value = [...employmentSelect.options].some(
        (opt) => opt.value === empStatus,
      )
        ? empStatus
        : "";
    }

    const subStatus = cleanValue(employee.sub_status).toUpperCase();
    const subStatusSelect = el("editSubStatus");
    if (subStatusSelect) {
      const valid = [...subStatusSelect.options].some(
        (opt) => opt.value === subStatus,
      );
      subStatusSelect.value = valid ? subStatus : "";
      toggleSubStatusOptions();
    }

    syncMultiUI(subStatus);

    populateEditBranch(
      [employee.branch],
      employee.brand,
      employee.branch,
      subStatus,
    );
    populateEditBrand(
      [employee.brand],
      employee.branch,
      employee.brand,
      subStatus,
    );

    const branches = safeArray(employee.roving_branches || p.roving_branches);
    const brands = safeArray(employee.multi_brands || p.multi_brands);

    requestAnimationFrame(() => {
      if (subStatus === "MULTI BRANCH" || subStatus === "HYBRID") {
        populateEditRoving(branches, employee.brand, employee.branch);
        updateBranchOptions();
      }
      if (subStatus === "MULTI BRAND" || subStatus === "HYBRID") {
        populateEditBrands(brands, employee.branch, employee.brand);
        updateBrandOptions();
      }
    });

    if (reasonSelect) reasonSelect.selectedIndex = 0;

    if (el("editDateSeparated"))
      el("editDateSeparated").value = cleanValue(employee.date_separated);
    if (el("editDateReturn"))
      el("editDateReturn").value = cleanValue(employee.date_of_return);
    if (el("editRemarks")) el("editRemarks").value = "";

    if (el("editLastUpdatedBy"))
      el("editLastUpdatedBy").value = cleanValue(employee.last_updated_by);
    if (el("editDateLastUpdated")) {
      el("editDateLastUpdated").value = employee.updated_at
        ? employee.updated_at.split(" ")[0]
        : "";
    }

    const editable = [
      "editReasonUpdate",
      "editDateSeparated",
      "editDateReturn",
      "editRemarks",
      "editAgency",
    ];

    modalEl.querySelectorAll("input, select, textarea").forEach((el) => {
      el.disabled = !editable.includes(el.id);
    });

    modal.show();
    loadHistory(employee.employee_id);

    toggleDateSeparated();
    toggleDateReturned();
    toggleReasonDates();
    toggleEmploymentDates();
    toggleTransferEditable();
    toggleStatusesEditable();
    toggleAddButtons();
    toggleBranchReasonOptions();
  } catch (err) {
    console.error(err);
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "Failed to load employee data",
    });
  }
}

// =========================
// DELEGATED CLICK HANDLER
// Works with server-side DataTables (rows are created dynamically)
// =========================
document.addEventListener("click", async function (e) {
  const row = e.target.closest(".clickable-row");
  if (!row) return;
  await openEmployeeModal(row.dataset.id);
});

// =========================
// SAVE BUTTON
// =========================
document.getElementById("saveBtn").addEventListener("click", async () => {
  const branch = document.getElementById("editBranch")?.value || "";
  const brand = document.getElementById("editBrand")?.value || "";
  const reason = (
    document.getElementById("editReasonUpdate").value || ""
  ).toUpperCase();

  const requiresAssignmentCheck =
    reason === "TRANSFER BRANCH" || reason === "REASSIGN";
  let isAvailable = true;

  if (requiresAssignmentCheck) {
    isAvailable = isComboAvailable(branch, brand);
    if (!isAvailable) {
      await Swal.fire({
        icon: "warning",
        title: "Slot Full",
        text: "This branch + brand assignment is already full.",
      });
      return;
    }
  }

  const result = await Swal.fire({
    icon: "warning",
    title: "Save Changes?",
    text: "Changes may affect assignments and history.",
    showCancelButton: true,
  });
  if (!result.isConfirmed) return;

  const dateSeparated = document.getElementById("editDateSeparated");
  const dateReturned = document.getElementById("editDateReturn");
  const startDate = document.getElementById("editStartDate");
  const endDate = document.getElementById("editEndDate");

  if (
    dateSeparated?.required &&
    !dateSeparated.value &&
    reason !== "MATERNITY LEAVE" &&
    reason !== "EMERGENCY LEAVE"
  ) {
    return Swal.fire({ icon: "warning", title: "Effectivity Date Required" });
  }
  if (
    dateSeparated?.required &&
    !dateSeparated.value &&
    (reason === "MATERNITY LEAVE" || reason === "EMERGENCY LEAVE")
  ) {
    return Swal.fire({ icon: "warning", title: "Start Date Required" });
  }
  if (reason === "MATERNITY LEAVE" && !dateReturned.value)
    return Swal.fire({ icon: "warning", title: "End Date Required" });
  if (reason === "EMERGENCY LEAVE" && !dateReturned.value)
    return Swal.fire({ icon: "warning", title: "End Date Required" });
  if (reason === "CHANGE EMPLOYMENT STATUS" && !startDate.value)
    return Swal.fire({ icon: "warning", title: "Start Date Required" });
  if (reason === "CHANGE SUB STATUS" && !startDate.value)
    return Swal.fire({ icon: "warning", title: "Effectivity Date Required" });
  if (reason === "ADD BRANCH/BRAND" && !startDate.value)
    return Swal.fire({ icon: "warning", title: "Effectivity Date Required" });
  if (!reason) return Swal.fire({ icon: "warning", title: "Reason Required" });

  const subStatus = (
    document.getElementById("editSubStatus")?.value || ""
  ).toUpperCase();

  const rovingBranches = Array.from(
    modalEl.querySelectorAll("#editRovingContainer select"),
  )
    .map((s) => s.value)
    .filter(Boolean);
  const multiBrands = Array.from(
    modalEl.querySelectorAll("#editMultiBrandContainer select"),
  )
    .map((s) => s.value)
    .filter(Boolean);

  if (subStatus === "MULTI BRANCH" && rovingBranches.length === 0) {
    return Swal.fire({
      icon: "warning",
      title: "Required",
      text: "Please select at least one branch.",
    });
  }
  if (subStatus === "MULTI BRAND" && multiBrands.length === 0) {
    return Swal.fire({
      icon: "warning",
      title: "Required",
      text: "Please select at least one brand.",
    });
  }

  const formData = new FormData();
  formData.set("id", document.getElementById("editPromodizerId").value);
  formData.set("employee_id", document.getElementById("editEmployeeId").value);
  formData.set(
    "employment_status",
    document.getElementById("editEmploymentStatus").value,
  );
  formData.set("sub_status", document.getElementById("editSubStatus").value);
  formData.set("reason_update", reason);
  formData.set("date_separated", dateSeparated.value);
  formData.set("date_returned", dateReturned.value);
  formData.set(
    "last_updated_by",
    document.getElementById("editLastUpdatedBy").value,
  );
  formData.set(
    "date_last_updated",
    document.getElementById("editDateLastUpdated").value,
  );
  formData.set("start_date", startDateInput.value);
  formData.set("end_date", endDateInput.value);
  formData.set("remarks", document.getElementById("editRemarks").value);
  formData.set("branch", branch);
  formData.set("brand", brand);
  formData.set("agency", document.getElementById("editAgency")?.value || "");

  rovingBranches.forEach((b) => formData.append("roving_branches[]", b));
  multiBrands.forEach((b) => formData.append("multi_brands[]", b));

  fetch("functions/update_promodizer.php", { method: "POST", body: formData })
    .then((res) => res.json())
    .then((data) => {
      if (data.status === "success") {
        Swal.fire("Success", data.message, "success").then(() => {
          window.location.href = "promodizers.php";
        });
      } else {
        Swal.fire("Error", data.message, "error");
      }
    })
    .catch((err) => {
      console.error(err);
      Swal.fire("Error", "Request failed", "error");
    });
});

async function loadBranchBrandPairs() {
  try {
    const res = await fetch("functions/get_available_branches_brands.php");
    branchBrandPairs = await res.json();
  } catch (err) {
    console.error("Failed to load branch-brand pairs", err);
  }
}

let agencyList = [];

const initPromise = Promise.all([loadAgencies(), loadBranchBrandPairs()]);

async function loadAgencies() {
  try {
    const res = await fetch("functions/get_agencies.php");
    agencyList = await res.json();
  } catch (err) {
    console.error("Failed to load agencies", err);
  }
}

function populateAgencyDropdown(selected = "") {
  const agencyEl = document.getElementById("editAgency");
  if (!agencyEl) return;

  const normalizedSelected = cleanValue(selected);
  const existsInList = agencyList.some(
    (a) => cleanValue(a).toUpperCase() === normalizedSelected.toUpperCase(),
  );

  let finalList = [...agencyList];
  if (normalizedSelected && !existsInList)
    finalList.unshift(normalizedSelected);

  agencyEl.innerHTML = finalList
    .map((a) => `<option value="${a}">${a}</option>`)
    .join("");

  agencyEl.value = normalizedSelected;
}

function getSelectedValues(container, selector) {
  return [...container.querySelectorAll(selector)]
    .map((s) => s.value)
    .filter((v) => v);
}

function updateBranchOptions() {
  const selects = editRovingContainer.querySelectorAll("select");
  const selectedValues = getSelectedValues(editRovingContainer, "select");
  const currentBrand = document.getElementById("editBrand")?.value;
  const baseBranch = document.getElementById("editBranch")?.value;

  const validBranches = branchBrandPairs
    .filter(
      (p) =>
        p.brand_name === currentBrand &&
        p.branch_code !== baseBranch &&
        (p.assigned_count < p.required_count ||
          selectedValues.includes(p.branch_code)),
    )
    .map((p) => p.branch_code);

  const uniqueBranches = [...new Set(validBranches)];

  selects.forEach((select) => {
    const currentValue = select.value;
    select.innerHTML =
      `<option value="">Select branch</option>` +
      uniqueBranches
        .filter(
          (branch) =>
            branch === currentValue || !selectedValues.includes(branch),
        )
        .map((branch) => {
          const displayName =
            branchBrandPairs.find(
              (p) => p.branch_code === branch && p.brand_name === currentBrand,
            )?.branch_name ?? branch;
          return `<option value="${branch}" ${branch === currentValue ? "selected" : ""}>${displayName}</option>`;
        })
        .join("");
  });
}

function updateBrandOptions() {
  const selects = editMultiBrandContainer.querySelectorAll("select");
  const selectedValues = getSelectedValues(editMultiBrandContainer, "select");
  const currentBranch = document.getElementById("editBranch")?.value;
  const baseBrand = document.getElementById("editBrand")?.value;

  const validBrands = branchBrandPairs
    .filter(
      (p) =>
        p.branch_code === currentBranch &&
        p.brand_name !== baseBrand &&
        (p.assigned_count < p.required_count ||
          selectedValues.includes(p.brand_name)),
    )
    .map((p) => p.brand_name);

  const uniqueBrands = [...new Set(validBrands)];

  selects.forEach((select) => {
    const currentValue = select.value;
    select.innerHTML =
      `<option value="">Select brand</option>` +
      uniqueBrands
        .filter(
          (brand) => !selectedValues.includes(brand) || brand === currentValue,
        )
        .map(
          (brand) =>
            `<option value="${brand}" ${brand === currentValue ? "selected" : ""}>${brand}</option>`,
        )
        .join("");
  });
}

function isComboAvailable(branch, brand) {
  const combo = branchBrandPairs.find(
    (p) => p.branch_code === branch && p.brand_name === brand,
  );
  if (!combo) return false;
  return combo.assigned_count < combo.required_count;
}

// =========================
// INIT LISTENERS
// =========================
document.addEventListener("DOMContentLoaded", async function () {
  await initPromise;

  if (reasonSelect) {
    reasonSelect.addEventListener("change", toggleDateSeparated);
    reasonSelect.addEventListener("change", toggleDateReturned);
    reasonSelect.addEventListener("change", toggleReasonDates);
    reasonSelect.addEventListener("change", toggleTransferEditable);
    reasonSelect.addEventListener("change", toggleStatusesEditable);
    reasonSelect.addEventListener("change", toggleAddButtons);
  }

  if (employmentStatusSelect) {
    employmentStatusSelect.addEventListener("change", toggleEmploymentDates);
  }

  editRovingContainer.addEventListener("click", (e) => {
    if (e.target.classList.contains("btn-add-branch")) {
      const row = e.target.closest(".roving-row");
      const clone = row.cloneNode(true);
      const select = clone.querySelector("select");
      if (select) {
        select.value = "";
        select.disabled = false;
      }
      const removeBtn = clone.querySelector(".btn-remove-branch");
      if (removeBtn) removeBtn.style.display = "inline-block";
      editRovingContainer.appendChild(clone);
      updateBranchOptions();
    }
    if (e.target.classList.contains("btn-remove-branch")) {
      e.target.closest(".roving-row").remove();
      updateBranchOptions();
    }
  });

  editMultiBrandContainer.addEventListener("click", (e) => {
    if (e.target.classList.contains("btn-add-brand")) {
      const row = e.target.closest(".brand-row");
      const clone = row.cloneNode(true);
      const select = clone.querySelector("select");
      if (select) {
        select.value = "";
        select.disabled = false;
      }
      const removeBtn = clone.querySelector(".btn-remove-brand");
      if (removeBtn) removeBtn.style.display = "inline-block";
      editMultiBrandContainer.appendChild(clone);
      updateBrandOptions();
    }
    if (e.target.classList.contains("btn-remove-brand")) {
      const allRows = editMultiBrandContainer.querySelectorAll(".brand-row");
      if (allRows.length > 1) {
        e.target.closest(".brand-row").remove();
        updateBrandOptions();
      } else {
        e.target.closest(".brand-row").querySelector("select").value = "";
      }
    }
  });

  const editSubStatus = document.getElementById("editSubStatus");
  if (editSubStatus) {
    editSubStatus.addEventListener("change", () => {
      const value = (editSubStatus.value || "").toUpperCase();
      toggleSubStatusOptions();
      syncMultiUI(value);
      toggleBranchReasonOptions();

      if (
        (value === "MULTI BRANCH" || value === "HYBRID") &&
        editRovingContainer.children.length === 0
      ) {
        populateEditRoving([""]);
        updateBranchOptions();
      }
      if (
        (value === "MULTI BRAND" || value === "HYBRID") &&
        editMultiBrandContainer.children.length === 0
      ) {
        populateEditBrands([""]);
        updateBrandOptions();
      }
    });
  }

  function validateMainAssignment() {
    const reason = (
      document.getElementById("editReasonUpdate")?.value || ""
    ).toUpperCase();
    if (reason !== "TRANSFER BRANCH" && reason !== "REASSIGN") return true;

    const branch = document.getElementById("editBranch")?.value;
    const brand = document.getElementById("editBrand")?.value;
    if (!branch || !brand) return true;

    if (!isComboAvailable(branch, brand)) {
      Swal.fire({
        icon: "warning",
        title: "Slot Full",
        text: "This branch + brand assignment is already full.",
      });
      return false;
    }
    return true;
  }

  const editBranch = document.getElementById("editBranch");
  const editBrand = document.getElementById("editBrand");

  editBranch?.addEventListener("change", () => {
    validateMainAssignment();
    updateBrandOptions();
    setCorpoFromBranch(editBranch.value);
  });

  editBrand?.addEventListener("change", () => {
    validateMainAssignment();
    updateBranchOptions();
  });

  editRovingContainer.addEventListener("change", (e) => {
    if (e.target.tagName !== "SELECT") return;
    const branch = e.target.value;
    const brand = document.getElementById("editBrand")?.value;
    if (branch && brand && !isComboAvailable(branch, brand)) {
      Swal.fire("Not Available", "This branch is full.", "warning");
      e.target.value = "";
      return;
    }
    updateBranchOptions();
  });

  editMultiBrandContainer.addEventListener("change", (e) => {
    if (e.target.tagName !== "SELECT") return;
    const brand = e.target.value;
    const branch = document.getElementById("editBranch")?.value;
    if (branch && brand && !isComboAvailable(branch, brand)) {
      Swal.fire("Not Available", "This brand is full.", "warning");
      e.target.value = "";
      return;
    }
    updateBrandOptions();
  });

  modalEl.addEventListener("hidden.bs.modal", () => {
    editRovingContainer.innerHTML = "";
    editMultiBrandContainer.innerHTML = "";
  });
});
