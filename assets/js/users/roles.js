const roleSelect = document.querySelector('select[name="role"]');
const btnSelectBranches = document.getElementById("btnSelectBranches");
const brandSelect = document.getElementById("brandSelect");
const departmentInput = document.getElementById("departmentInput");

function disableBranchSelect() {
  if (btnSelectBranches) {
    btnSelectBranches.disabled = true;
  }
}

function enableBranchSelect() {
  if (btnSelectBranches) {
    btnSelectBranches.disabled = false;
  }
}

function updateFieldsByRole() {
  const role = roleSelect.value;

  // reset first
  disableBranchSelect();
  departmentInput.disabled = true;

  if (role === "staff") {
    enableBranchSelect();
  } else if (role === "inhouse_manager") {
    departmentInput.disabled = false;
    if (brandSelect) brandSelect.disabled = false;
  } else if (role === "branch_manager") {
    enableBranchSelect();
  } else if (role === "supervisor") {
    enableBranchSelect();
  }
}

roleSelect.addEventListener("change", updateFieldsByRole);

updateFieldsByRole();
