const roleSelect = document.querySelector('select[name="role"]');
const branchSelect = document.getElementById("branchSelect");
const brandSelect = document.getElementById("brandSelect");
const departmentInput = document.getElementById("departmentInput");

// Helper functions for the checkbox group
function disableBranchSelect() {
  document.getElementById("branchSearch").disabled = true;
  branchSelect
    .querySelectorAll('input[type="checkbox"]')
    .forEach((cb) => (cb.disabled = true));
}

function enableBranchSelect() {
  // Enable search input
  document.getElementById("branchSearch").disabled = false;

  // Enable checkboxes
  branchSelect
    .querySelectorAll('input[type="checkbox"]')
    .forEach((cb) => (cb.disabled = false));
}

function updateFieldsByRole() {
  const role = roleSelect.value;

  // reset first
  disableBranchSelect();
  departmentInput.disabled = true;

  if (role === "staff") {
    // HR: branch + department only
    enableBranchSelect();
  } else if (role === "inhouse_manager") {
    // inhouse manager: branch + brand only
    departmentInput.disabled = false;
    brandSelect.disabled = false;
  } else if (role === "branch_manager") {
    // branch manager: branch + department only
    enableBranchSelect();
  }
}

roleSelect.addEventListener("change", updateFieldsByRole);

// run on page load in case of pre-filled value
updateFieldsByRole();
