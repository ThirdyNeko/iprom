// assets/js/users/roles.js

const roleSelect = document.querySelector('select[name="role"]');
const branchSelect = document.getElementById("branchSelect");
const brandSelect = document.getElementById("brandSelect");
const departmentInput = document.getElementById("departmentInput");
const branchSectionLabel = document.getElementById("branchSectionLabel");
const branchSingleHint = document.getElementById("branchSingleHint");

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

function updateFieldsByRole() {
  const role = roleSelect.value;

  disableBranchSelect();
  setBranchLabelMode(false);

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