// assets/js/users/roles.js

const roleSelect = document.querySelector('select[name="role"]');
const branchSelect = document.getElementById("branchSelect");
const brandSelect = document.getElementById("brandSelect");
const departmentInput = document.getElementById("departmentInput");
const branchSectionLabel = document.getElementById("branchSectionLabel");
const branchSingleHint = document.getElementById("branchSingleHint");
const branchSectionWrapper = document.getElementById("branchSectionWrapper");

const AUDIT_ROLES = ["audit_manager", "audit_supervisor", "audit_staff"];

function disableBranchSelect() {
  document.getElementById("branchSearch").disabled = true;

  branchSelect.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
    cb.checked = false;
    cb.disabled = true;
  });

  sortCreateBranches();
  updateCreateBranchCounter();
}

function enableBranchSelect() {
  document.getElementById("branchSearch").disabled = false;

  branchSelect
    .querySelectorAll('input[type="checkbox"]')
    .forEach((cb) => (cb.disabled = false));

  sortCreateBranches();
  updateCreateBranchCounter();
}

function setBranchLabelMode(isSingle) {
  if (branchSectionLabel) {
    branchSectionLabel.textContent = isSingle ? "Branch" : "Branches";
  }
  if (branchSingleHint) {
    branchSingleHint.classList.toggle("d-none", !isSingle);
  }
}

function setBranchSectionVisible(visible) {
  if (branchSectionWrapper) {
    branchSectionWrapper.style.display = visible ? "" : "none";
  }
}

function updateFieldsByRole() {
  const role = roleSelect.value;

  disableBranchSelect();
  setBranchLabelMode(false);

  // Audit roles don't take a branch assignment at all — hide the
  // section outright rather than just leaving it disabled/empty.
  if (AUDIT_ROLES.includes(role)) {
    setBranchSectionVisible(false);
    return;
  }

  setBranchSectionVisible(true);

  if (role === "staff") {
    enableBranchSelect();
  } else if (role === "inhouse_manager") {
    if (brandSelect) brandSelect.disabled = false;
  } else if (role === "branch_manager") {
    enableBranchSelect();
    setBranchLabelMode(true);
  }
}

roleSelect.addEventListener("change", updateFieldsByRole);

updateFieldsByRole();