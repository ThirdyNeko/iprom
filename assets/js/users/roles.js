const roleSelect = document.querySelector('select[name="role"]');
const branchSelect = document.getElementById("branchSelect");
const brandSelect = document.getElementById("brandSelect");
const departmentInput = document.getElementById("departmentInput");

function updateFieldsByRole() {
  const role = roleSelect.value;

  // reset first
  branchSelect.disabled = true;
  brandSelect.disabled = true;
  departmentInput.disabled = true;

  if (role === "hr") {
    // HR: branch + department only
    departmentInput.disabled = false;
  } else if (role === "inhouse_manager") {
    // inhouse manager: branch + brand only
    departmentInput.disabled = false;
    brandSelect.disabled = false;
  } else if (role === "branch_manager") {
    // branch manager: branch + department only
    branchSelect.disabled = false;
  }
}

roleSelect.addEventListener("change", updateFieldsByRole);

// run on page load in case of pre-filled value
updateFieldsByRole();
