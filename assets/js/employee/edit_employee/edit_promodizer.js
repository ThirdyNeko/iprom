let branchBrandPairs = [];
let provinceList = [];

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

// Formats the stored categories value ("ALL" or "TV,DA,...") into a
// friendlier display string for the read-only field.
function formatCategoriesDisplay(value) {
  const cleaned = cleanValue(value);
  if (!cleaned) return "";
  if (cleaned.toUpperCase() === "ALL") return "All";
  return cleaned
    .split(",")
    .map((c) => c.trim())
    .filter(Boolean)
    .join(", ");
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

  // branch_manager can never print, full stop — takes priority over
  // isAdmin/canPrintLOA below.
  if (isBranchManagerRole()) {
    printBtn.disabled = true;
    return;
  }

  if (isStaffRole()) {
    printBtn.disabled = true;
    return;
  }

  const rawCanPrintLOA = window.canPrintLOA;
  const isAdmin = ["admin", "super_admin"].includes(window.userRole || "");
  const canPrintLOA = Number(rawCanPrintLOA || 0) === 1;

  const allowed = isAdmin || canPrintLOA;
  printBtn.disabled = !allowed || status === "INACTIVE";
}

// =========================
// ROLE HELPERS
// =========================
function isBranchManagerRole() {
  return (window.userRole || "").toLowerCase() === "branch_manager";
}

function isStaffRole() {
  return (window.userRole || "").toLowerCase() === "staff";
}

// Fully locks the page down to read-only for branch_manager.
// Must be called AFTER all the other toggle*() functions run in
// loadEmployeePage(), since several of those explicitly re-enable
// specific fields (start date, agency, address, etc.) depending on
// the selected reason — calling this first would just get
// overridden by those.
function lockPageForBranchManager() {
  if (!pageEl) return;

  pageEl.querySelectorAll("input, select, textarea").forEach((el) => {
    el.disabled = true;
  });

  pageEl
    .querySelectorAll(
      ".btn-add-branch, .btn-add-brand, .btn-remove-branch, .btn-remove-brand",
    )
    .forEach((btn) => {
      btn.style.display = "none";
    });

  const saveBtn = document.getElementById("saveBtn");
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.style.display = "none";
  }

  const alertBox = document.getElementById("editAlert");
  if (alertBox) {
    alertBox.innerHTML =
      '<div class="alert alert-warning mt-2 mb-0">You have read-only access to this record.</div>';
  }
}

// Masks a single field's displayed value with asterisks. Selects get
// replaced with one disabled masked option (so the actual place name
// never touches the DOM); date/text inputs get their value swapped
// for a fixed-length mask. Date inputs are switched to type="text"
// first, since a native <input type="date"> silently blanks out any
// non-date string assigned to .value.
function maskFieldValue(el) {
  if (!el) return;
  const MASK = "********";

  if (el.tagName === "SELECT") {
    el.innerHTML = `<option value="" selected>${MASK}</option>`;
    el.disabled = true;
    return;
  }

  if (el.tagName === "INPUT") {
    if (el.type === "date") el.type = "text";
    el.value = MASK;
    el.disabled = true;
  }
}

// Masks Birthday and full Address (province/municipality/barangay/
// street) for branch_manager only. Safe to call multiple times.
function maskSensitiveFieldsForBranchManager() {
  if (!isBranchManagerRole()) return;

  maskFieldValue(document.getElementById("editBirthday"));
  maskFieldValue(editProvince);
  maskFieldValue(editMunicipality);
  maskFieldValue(editBarangay);
  maskFieldValue(editStreet);
}

// =========================
// ELEMENT REFERENCES
// =========================
const pageEl = document.getElementById("editPromodizerPage");
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

// NEW: personal / address element references
const editMaritalStatus = document.getElementById("editMaritalStatus");
const editContactNumber = document.getElementById("editContactNumber");
const editBiometricNumber = document.getElementById("editBiometricNumber");
const editCategories = document.getElementById("editCategories");
const editProvince = document.getElementById("editProvince");
const editProvinceName = document.getElementById("editProvinceName");
const editMunicipality = document.getElementById("editMunicipality");
const editMunicipalityName = document.getElementById("editMunicipalityName");
const editBarangay = document.getElementById("editBarangay");
const editBarangayName = document.getElementById("editBarangayName");
const editStreet = document.getElementById("editStreet");

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

  // Check the EMPLOYEE'S STORED reason (from DB), not the live dropdown
  // value — the dropdown gets reset to blank on every load, so relying
  // on reasonSelect.value here would never catch an already-blacklisted
  // employee.
  const storedReason = (window.currentEmployee?.reason_update || "")
    .trim()
    .toUpperCase();
  const isBlacklisted = storedReason === "BLACKLISTED / AWOL / TERMINATED";
  const isDeceased = storedReason === "DECEASED";

  const addOpt = reasonSelect.querySelector('option[value="ADD BRANCH/BRAND"]');
  const removeOpt = reasonSelect.querySelector(
    'option[value="REMOVE BRANCH/BRAND"]',
  );
  const transferOpt = reasonSelect.querySelector(
    'option[value="TRANSFER BRANCH"]',
  );

  if (addOpt) addOpt.disabled = isStationary;
  if (removeOpt) removeOpt.disabled = isStationary;
  if (transferOpt) {
    transferOpt.disabled = !isStationary;
    transferOpt.style.color = !isStationary ? "#aaa" : "";
  }

  const inactiveOnly = new Set([
    "BLACKLISTED / AWOL / TERMINATED",
    "PULL-OUT / END OF CONTRACT",
    "RESIGNED",
    "DECEASED",
  ]);

  reasonSelect.querySelectorAll("option").forEach((opt) => {
    if (opt.value === "") return;
    if (opt === transferOpt) return; // already handled above
    if (isInactive) {
      const belongs = inactiveOnly.has(opt.value);
      opt.disabled = !belongs;
      opt.style.color = !belongs ? "#aaa" : "";
    } else {
      opt.style.color = "";
    }
  });

  // TRANSFER BRANCH should also be disabled when INACTIVE
  if (transferOpt && isInactive) {
    transferOpt.disabled = true;
    transferOpt.style.color = "#aaa";
  }

  // Terminal state: the employee's STORED reason is
  // "BLACKLISTED / AWOL / TERMINATED" → lock every option, overriding
  // the isInactive block above.
  if (isBlacklisted) {
    reasonSelect.querySelectorAll("option").forEach((opt) => {
      if (opt.value === "") return; // placeholder stays untouched
      opt.disabled = true;
      opt.style.color = "#aaa";
    });
  }

  if (isDeceased) {
    reasonSelect.querySelectorAll("option").forEach((opt) => {
      if (opt.value === "") return; // placeholder stays untouched
      opt.disabled = true;
      opt.style.color = "#aaa";
    });
  }

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
    reason === "DECEASED" ||
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
  console.error("Page elements not found. Check edit_promodizer.php markup.");
}

const showDateSeparatedReasons = [
  "RESIGNED",
  "DECEASED",
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

// =========================
// NEW: gate personal info / address fields by reason
// =========================
function toggleContactAddressEditable() {
  if (!reasonSelect) return;
  const reason = (reasonSelect.value || "").toUpperCase();

  const enableMaritalStatus = reason === "UPDATE MARITAL STATUS";
  const enableContactNumber = reason === "UPDATE CONTACT NUMBER";
  const enableAddress = reason === "UPDATE ADDRESS";
  const enableBiometricNumber = reason === "UPDATE BIOMETRIC NUMBER";

  if (editMaritalStatus) editMaritalStatus.disabled = !enableMaritalStatus;
  if (editContactNumber) editContactNumber.disabled = !enableContactNumber;
  if (editBiometricNumber)
    editBiometricNumber.disabled = !enableBiometricNumber;

  if (editProvince) editProvince.disabled = !enableAddress;
  if (editStreet) editStreet.disabled = !enableAddress;

  // municipality/barangay stay disabled unless address editing is on
  // AND their parent has a value (cascade dependency)
  if (editMunicipality) {
    editMunicipality.disabled = !enableAddress || !editProvince?.value;
  }
  if (editBarangay) {
    editBarangay.disabled = !enableAddress || !editMunicipality?.value;
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

  const status = (document.getElementById("editStatus")?.value || "")
    .trim()
    .toUpperCase();
  const isInactive = status === "INACTIVE";

  let list = [...new Set(safeArray(branches))];

  const selectedReason = cleanValue(reasonSelect?.value).toUpperCase();
  const canAddBranch =
    !isInactive &&
    (selectedReason === "ADD BRANCH/BRAND" ||
      selectedReason === "CHANGE SUB STATUS");

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

      const disableSelect = isInactive || isExisting;
      const hideRemove = isInactive || isExisting || index === 0;

      return `
        <div class="d-flex gap-2 mb-2 align-items-center roving-row">
          <select class="form-control" ${disableSelect ? "disabled" : ""}>
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
          <button type="button" class="btn btn-danger btn-remove-branch" style="${hideRemove ? "display:none;" : ""}">−</button>
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

  const status = (document.getElementById("editStatus")?.value || "")
    .trim()
    .toUpperCase();
  const isInactive = status === "INACTIVE";

  let list = [...new Set(safeArray(brands))];

  const selectedReason = cleanValue(reasonSelect?.value).toUpperCase();
  const canAddBrand =
    !isInactive &&
    (selectedReason === "ADD BRANCH/BRAND" ||
      selectedReason === "CHANGE SUB STATUS");

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

      const disableSelect = isInactive || isExisting;
      const hideRemove = isInactive || isExisting || index === 0;

      return `
        <div class="d-flex gap-2 mb-2 align-items-center brand-row">
          <select class="form-control" ${disableSelect ? "disabled" : ""}>
            <option value="">Select brand</option>
            ${uniqueBrands
              .map(
                (brand) =>
                  `<option value="${brand}" ${brand === b ? "selected" : ""}>${brand}</option>`,
              )
              .join("")}
          </select>
          <button type="button" class="btn btn-success btn-add-brand" style="${canAddBrand ? "" : "display:none;"}">+</button>
          <button type="button" class="btn btn-danger btn-remove-brand" style="${hideRemove ? "display:none;" : ""}">−</button>
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

function resetEditPage() {
  editRovingContainer.innerHTML = "";
  editMultiBrandContainer.innerHTML = "";

  const protectedSelects = [
    "editReasonUpdate",
    "editEmploymentStatus",
    "editSubStatus",
  ];

  pageEl.querySelectorAll("select").forEach((s) => {
    if (!protectedSelects.includes(s.id)) {
      s.value = "";
      s.disabled = false;
    }
  });

  if (editAgency) {
    editAgency.value = "";
    editAgency.disabled = true;
  }

  // NEW: reset personal / address fields
  if (editContactNumber) editContactNumber.value = "";
  if (editCategories) editCategories.value = "";
  if (editStreet) editStreet.value = "";
  if (editProvinceName) editProvinceName.value = "";
  if (editMunicipalityName) editMunicipalityName.value = "";
  if (editBarangayName) editBarangayName.value = "";
  if (editMunicipality) {
    editMunicipality.innerHTML =
      '<option value="" disabled selected>-- Select Municipality --</option>';
    editMunicipality.disabled = true;
  }
  if (editBarangay) {
    editBarangay.innerHTML =
      '<option value="" disabled selected>-- Select Barangay --</option>';
    editBarangay.disabled = true;
  }

  // Reset picture box back to placeholder until the real employee_id
  // is loaded and re-fetched.
  if (typeof window.loadEmployeePicture === "function") {
    window.loadEmployeePicture(null);
  }
}

// =========================
// NEW: PH address cascade (edit page)
// =========================
async function loadProvinceListOnce() {
  try {
    const res = await fetch("functions/get_provinces.php");
    provinceList = await res.json();
  } catch (err) {
    console.error("Failed to load provinces", err);
    provinceList = [];
  }
}

function populateEditProvinceSelect(selectedCode = "") {
  if (!editProvince) return;
  editProvince.innerHTML =
    '<option value="" disabled selected>-- Select Province --</option>' +
    provinceList
      .map(
        (p) =>
          `<option value="${p.code}" ${p.code === selectedCode ? "selected" : ""}>${p.name}</option>`,
      )
      .join("");

  if (editProvinceName) {
    const match = provinceList.find((p) => p.code === selectedCode);
    editProvinceName.value = match ? match.name : "";
  }
}

async function loadEditMunicipalities(provinceCode, selectedCode = "") {
  if (!editMunicipality) return;

  editMunicipality.innerHTML =
    '<option value="" disabled selected>-- Select Municipality --</option>';
  if (editMunicipalityName) editMunicipalityName.value = "";

  if (!provinceCode) {
    editMunicipality.disabled = true;
    return;
  }

  try {
    const res = await fetch(
      `functions/get_municipalities.php?province_code=${encodeURIComponent(provinceCode)}`,
    );
    const list = await res.json();

    editMunicipality.innerHTML =
      '<option value="" disabled selected>-- Select Municipality --</option>' +
      list
        .map(
          (m) =>
            `<option value="${m.code}" ${m.code === selectedCode ? "selected" : ""}>${m.name}</option>`,
        )
        .join("");

    if (editMunicipalityName) {
      const match = list.find((m) => m.code === selectedCode);
      editMunicipalityName.value = match ? match.name : "";
    }
  } catch (err) {
    console.error("Failed to load municipalities", err);
  }
}

async function loadEditBarangays(municipalityCode, selectedCode = "") {
  if (!editBarangay) return;

  editBarangay.innerHTML =
    '<option value="" disabled selected>-- Select Barangay --</option>';
  if (editBarangayName) editBarangayName.value = "";

  if (!municipalityCode) {
    editBarangay.disabled = true;
    return;
  }

  try {
    const res = await fetch(
      `functions/get_barangays.php?municipality_code=${encodeURIComponent(municipalityCode)}`,
    );
    const list = await res.json();

    editBarangay.innerHTML =
      '<option value="" disabled selected>-- Select Barangay --</option>' +
      list
        .map(
          (b) =>
            `<option value="${b.code}" ${b.code === selectedCode ? "selected" : ""}>${b.name}</option>`,
        )
        .join("");

    if (editBarangayName) {
      const match = list.find((b) => b.code === selectedCode);
      editBarangayName.value = match ? match.name : "";
    }
  } catch (err) {
    console.error("Failed to load barangays", err);
  }
}

// =========================
// LOAD EMPLOYEE INTO PAGE
// =========================
async function loadEmployeePage(id) {
  await initPromise;
  resetEditPage();

  try {
    const res = await fetch(`functions/get_employee.php?id=${id}`);
    const p = await res.json();

    if (!p || !p.id) {
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "Employee not found",
      }).then(() => {
        window.location.href = "promodizers.php";
      });
      return;
    }

    const employee = {
      id: p.id,
      employee_id: p.employee_id,
      loa_code: p.loa_code,
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
      // NEW: personal / address fields
      marital_status: p.marital_status,
      contact_number: p.contact_number,
      biometric_number: p.biometric_number,
      categories: p.categories,
      province: p.province,
      province_name: p.province_name,
      municipality: p.municipality,
      municipality_name: p.municipality_name,
      barangay: p.barangay,
      barangay_name: p.barangay_name,
      street: p.street,
    };

    window.currentEmployee = employee;
    window.canPrintLOA = p.print_loa ?? 0;

    // EMPLOYEE PICTURE — check_employee_picture.php keys off the
    // actual employee_id string (e.g. "EMP-..."), which is only
    // available now, not the promodizer record id from the URL.
    if (typeof window.loadEmployeePicture === "function") {
      window.loadEmployeePicture(employee.employee_id);
    }

    const el = (id) => document.getElementById(id);

    if (el("editPromodizerId")) el("editPromodizerId").value = employee.id;
    if (el("editEmployeeId")) el("editEmployeeId").value = employee.employee_id;
    if (el("editLoaCode")) el("editLoaCode").value = employee.loa_code;
    if (el("editFirstName"))
      el("editFirstName").value = cleanValue(employee.first_name);
    if (el("editLastName"))
      el("editLastName").value = cleanValue(employee.last_name);
    if (el("editMiddleName"))
      el("editMiddleName").value = cleanValue(employee.middle_name);
    if (el("editSuffix")) el("editSuffix").value = cleanValue(employee.suffix);

    if (el("editBranch")) {
      populateEditBranch([employee.branch], employee.brand, employee.branch);
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
    if (el("editStatus")) {
      const rawStatus = cleanValue(employee.status);
      el("editStatus").value =
        rawStatus.toUpperCase() === "PENDING" ? "QUEUED" : rawStatus || "-";
    }
    if (el("editLastAssignedBy"))
      el("editLastAssignedBy").value = cleanValue(employee.last_assigned_by);

    // NEW: marital status / contact number / categories / street (direct fields)
    if (editMaritalStatus)
      editMaritalStatus.value = cleanValue(employee.marital_status) || "";
    if (editContactNumber)
      editContactNumber.value = cleanValue(employee.contact_number);
    if (editBiometricNumber)
      editBiometricNumber.value = cleanValue(employee.biometric_number);
    if (editCategories)
      editCategories.value = formatCategoriesDisplay(employee.categories);
    if (editStreet) editStreet.value = cleanValue(employee.street);

    // NEW: address cascade — populate province, then load matching
    // municipality/barangay lists and pre-select the saved codes
    populateEditProvinceSelect(employee.province || "");
    await loadEditMunicipalities(
      employee.province || "",
      employee.municipality || "",
    );
    await loadEditBarangays(
      employee.municipality || "",
      employee.barangay || "",
    );

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

      // Re-apply the branch_manager lock after this async UI is
      // populated, since populateEditRoving()/populateEditBrands()
      // render fresh <select> elements that would otherwise be
      // enabled by default.
      if (isBranchManagerRole()) {
        lockPageForBranchManager();
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

    const isBranchManager = isBranchManagerRole();

    const editable = [
      "editReasonUpdate",
      "editDateSeparated",
      "editDateReturn",
      "editRemarks",
      "editAgency",
    ];

    pageEl.querySelectorAll("input, select, textarea").forEach((el) => {
      el.disabled = isBranchManager ? true : !editable.includes(el.id);
    });

    loadHistory(employee.employee_id);
    checkPrintBtnState();

    toggleDateSeparated();
    toggleDateReturned();
    toggleReasonDates();
    toggleEmploymentDates();
    toggleTransferEditable();
    toggleStatusesEditable();
    toggleContactAddressEditable();
    toggleAddButtons();
    toggleBranchReasonOptions();

    // Must run last: several of the toggle*() calls above explicitly
    // re-enable specific fields depending on the selected reason, so
    // the lock has to be applied after all of them to actually stick.
    if (isBranchManager) {
      maskSensitiveFieldsForBranchManager();
      lockPageForBranchManager();
    }
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
// SAVE BUTTON
// =========================
document.getElementById("saveBtn").addEventListener("click", async () => {
  if (isBranchManagerRole()) return;

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

  // NEW: marital status / contact number / address validation
  if (reason === "UPDATE MARITAL STATUS" && !editMaritalStatus?.value) {
    return Swal.fire({
      icon: "warning",
      title: "Missing Data",
      text: "Please select a Marital Status.",
    });
  }
  if (reason === "UPDATE CONTACT NUMBER") {
    if (!editContactNumber?.value) {
      return Swal.fire({
        icon: "warning",
        title: "Missing Data",
        text: "Please enter a Contact Number.",
      });
    }
    if (!/^09\d{9}$/.test(editContactNumber.value)) {
      return Swal.fire({
        icon: "warning",
        title: "Invalid Contact Number",
        text: "Contact number must be 11 digits and start with 09.",
      });
    }
  }
  if (reason === "UPDATE BIOMETRIC NUMBER") {
    // Optional field: only validate format if the user actually
    // entered something. Blank is allowed.
    if (
      editBiometricNumber?.value &&
      !/^\d{7}$/.test(editBiometricNumber.value)
    ) {
      return Swal.fire({
        icon: "warning",
        title: "Invalid Biometric Number",
        text: "Biometric number must be exactly 7 digits, or left blank.",
      });
    }
  }
  if (
    reason === "UPDATE ADDRESS" &&
    (!editProvince?.value ||
      !editMunicipality?.value ||
      !editBarangay?.value ||
      !editStreet?.value)
  ) {
    return Swal.fire({
      icon: "warning",
      title: "Missing Address",
      text: "Please complete Province, Municipality, Barangay, and Street.",
    });
  }

  if (!reason) return Swal.fire({ icon: "warning", title: "Reason Required" });

  const subStatus = (
    document.getElementById("editSubStatus")?.value || ""
  ).toUpperCase();

  const rovingBranches = Array.from(
    pageEl.querySelectorAll("#editRovingContainer select"),
  )
    .map((s) => s.value)
    .filter(Boolean);
  const multiBrands = Array.from(
    pageEl.querySelectorAll("#editMultiBrandContainer select"),
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
  formData.set("loa_code", document.getElementById("editLoaCode").value);
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

  // NEW: personal / address fields
  formData.set("marital_status", editMaritalStatus?.value || "");
  formData.set("contact_number", editContactNumber?.value || "");

  // Biometric number is optional — send empty string through as-is;
  // update_promodizer.php should treat an empty value as NULL.
  formData.set("biometric_number", editBiometricNumber?.value || "");

  formData.set("province", editProvince?.value || "");
  formData.set("province_name", editProvinceName?.value || "");
  formData.set("municipality", editMunicipality?.value || "");
  formData.set("municipality_name", editMunicipalityName?.value || "");
  formData.set("barangay", editBarangay?.value || "");
  formData.set("barangay_name", editBarangayName?.value || "");
  formData.set("street", editStreet?.value || "");

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

const initPromise = Promise.all([
  loadAgencies(),
  loadBranchBrandPairs(),
  loadProvinceListOnce(),
]);

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
// INIT
// =========================
document.addEventListener("DOMContentLoaded", async function () {
  if (reasonSelect) {
    reasonSelect.addEventListener("change", toggleDateSeparated);
    reasonSelect.addEventListener("change", toggleDateReturned);
    reasonSelect.addEventListener("change", toggleReasonDates);
    reasonSelect.addEventListener("change", toggleTransferEditable);
    reasonSelect.addEventListener("change", toggleStatusesEditable);
    reasonSelect.addEventListener("change", toggleContactAddressEditable);
    reasonSelect.addEventListener("change", toggleAddButtons);
  }

  if (employmentStatusSelect) {
    employmentStatusSelect.addEventListener("change", toggleEmploymentDates);
  }

  const editStatus = document.getElementById("editStatus");
  if (editStatus) {
    editStatus.addEventListener("change", checkPrintBtnState);
    editStatus.addEventListener("input", checkPrintBtnState);
  }

  // Biometric number: digits only, max 7 (optional field)
  editBiometricNumber?.addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, "").slice(0, 7);
  });

  // NEW: address cascade change listeners
  editProvince?.addEventListener("change", async (e) => {
    const code = e.target.value;
    if (editProvinceName)
      editProvinceName.value =
        e.target.options[e.target.selectedIndex]?.text || "";
    await loadEditMunicipalities(code);
    toggleContactAddressEditable();
  });

  editMunicipality?.addEventListener("change", async (e) => {
    const code = e.target.value;
    if (editMunicipalityName)
      editMunicipalityName.value =
        e.target.options[e.target.selectedIndex]?.text || "";
    await loadEditBarangays(code);
    toggleContactAddressEditable();
  });

  editBarangay?.addEventListener("change", (e) => {
    if (editBarangayName)
      editBarangayName.value =
        e.target.options[e.target.selectedIndex]?.text || "";
  });

  editContactNumber?.addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, "").slice(0, 11);
  });

  editStreet?.addEventListener("input", function () {
    this.value = this.value.toUpperCase();
  });

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

  // Load the employee record this page was opened for
  if (window.editEmployeeRecordId) {
    loadEmployeePage(window.editEmployeeRecordId);
  }
});
