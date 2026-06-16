// assets/js/users/roles.js

const roleSelect = document.querySelector('select[name="role"]');
const branchSelect = document.getElementById("branchSelect");
const brandSelect = document.getElementById("brandSelect");
const departmentInput = document.getElementById("departmentInput");

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

function updateFieldsByRole() {
  const role = roleSelect.value;

  disableBranchSelect();

  if (role === "staff") {
    enableBranchSelect();
  } else if (role === "inhouse_manager") {
    if (brandSelect) brandSelect.disabled = false;
  } else if (role === "branch_manager") {
    enableBranchSelect();
  }
}

roleSelect.addEventListener("change", updateFieldsByRole);

updateFieldsByRole();
