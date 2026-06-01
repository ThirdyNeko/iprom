let assignmentModalDisabled = false;
let currentAssigned = 0;

// =========================
// STATUS BADGE
// =========================
function getStatusBadge(required, assigned) {
  const shortage = required - assigned;
  if (required === 0) {
    return `<span class="badge bg-secondary">INACTIVE</span>`;
  }

  if (assigned === 0) {
    return `<span class="badge bg-danger">VACANT</span>`;
  }

  if (shortage > 0) {
    return `<span class="badge bg-orange">INCOMPLETE: ${shortage}</span>`;
  }

  return `<span class="badge bg-success">COMPLETE</span>`;
}

// =========================
// OPEN MODAL (FETCH FROM DB)
// =========================
$(document).on("click", "#assignmentTable tbody tr", function () {
  const branch = $(this).data("branch");
  const brand = $(this).data("brand");

  if (!branch || !brand) return;

  openAssignmentModal(branch, brand);
});

// =========================
// MAIN MODAL LOADER
// =========================
async function openAssignmentModal(branch, brand) {
  assignmentModalDisabled = true;
  currentAssigned = 0;

  $("#modalAssignedList").html('<small class="text-muted">Loading...</small>');

  const modalEl = document.getElementById("assignmentModal");
  bootstrap.Modal.getOrCreateInstance(modalEl).show();

  try {
    const res = await fetch("functions/fetch_assignments.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        mode: "modal",
        branch,
        brand,
      }),
    });

    const result = await res.json();

    if (!result || result.status !== "success") {
      throw new Error("Invalid response");
    }

    const data = result.data;

    assignmentModalDisabled = false;

    // store context
    $("#assignmentModal").data("branch", data.branch);
    $("#assignmentModal").data("brand", data.brand);

    // update header info
    $("#modalBranch").text(data.branch);
    $("#modalBrand").text(data.brand);
    $("#modalRequired").val(data.required);

    currentAssigned = data.employees?.length || 0;

    $("#modalStatus").html(getStatusBadge(data.required, data.assigned));

    $("#modalUpdated").text(
      data.updated
        ? new Date(data.updated.replace(" ", "T")).toLocaleDateString("en-CA")
        : "-",
    );

    $("#modalUpdatedBy").text(data.updated_by || "-");

    renderAssignedList(data.employees || [], data.required, data.assigned);
  } catch (err) {
    console.error(err);

    assignmentModalDisabled = true;

    $("#modalAssignedList").html(
      '<div class="alert alert-danger mb-0">Failed to load data.</div>',
    );
  }
}

// =========================
// RENDER ASSIGNED LIST
// =========================
function renderAssignedList(employees, required, assigned) {
  let html = "";

  if (!employees.length) {
    html = '<small class="text-muted">No employee assigned</small>';
  } else {
    html = '<ul class="list-group list-group-flush">';

    employees.forEach((emp) => {
      html += `
        <li class="list-group-item d-flex justify-content-between align-items-center py-1">
          <span>${emp.first_name} ${emp.last_name}</span>
          <button class="btn btn-sm btn-primary edit-btn" data-id="${emp.id}">
            Edit
          </button>
        </li>
      `;
    });

    html += "</ul>";
  }

  if (assigned < required) {
    html += `
    <div class="mt-2 text-left">
      <button type="button" class="btn btn-sm btn-primary add-promodizer-btn">
        + Add Promodiser
      </button>
    </div>
  `;
  }

  $("#modalAssignedList").html(html);
  $("#modalStatus").html(getStatusBadge(required, assigned));
}

// =========================
// SAVE REQUIRED
// =========================
const saveRequiredBtn = document.getElementById("saveRequiredBtn");

if (saveRequiredBtn) {
  saveRequiredBtn.addEventListener("click", async () => {
    const modal = $("#assignmentModal");
    const branch = modal.data("branch");
    const brand = modal.data("brand");

    const required = Number($("#modalRequired").val());

    if (!branch || !brand) {
      return Swal.fire("Missing Data", "No assignment selected.", "error");
    }

    if (assignmentModalDisabled) {
      return Swal.fire("Unavailable", "Cannot update right now.", "error");
    }

    if (isNaN(required) || required < 0) {
      return Swal.fire("Invalid Input", "Required must be valid.", "error");
    }

    const currentAssigned = $("#modalAssignedList .list-group-item").length;
    if (required < currentAssigned) {
      return Swal.fire(
        "Invalid Update",
        `Required (${required}) cannot be less than Assigned (${currentAssigned}).`,
        "error",
      );
    }

    if (required === 0) {
      const confirmZero = await Swal.fire({
        icon: "warning",
        title: "Full Pull-Out Warning",
        text: "This will remove ALL assignments. Continue?",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Yes, continue",
      });

      if (!confirmZero.isConfirmed) return;
    }

    const confirm = await Swal.fire({
      icon: "warning",
      title: "Update Required?",
      text: `Update required count for ${branch} - ${brand}?`,
      showCancelButton: true,
      confirmButtonText: "Yes, update",
    });

    if (!confirm.isConfirmed) return;

    try {
      const res = await fetch("functions/update_required.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ branch, brand, required }),
      });

      const result = await res.json();

      if (result.status !== "success") {
        throw new Error(result.message || "Update failed");
      }

      await Swal.fire({
        icon: "success",
        title: "Updated",
        timer: 1200,
        showConfirmButton: false,
      });

      window.assignmentTable.ajax.reload(null, false);

      bootstrap.Modal.getInstance(
        document.getElementById("assignmentModal"),
      ).hide();
    } catch (err) {
      console.error(err);

      Swal.fire("Error", "Server error occurred.", "error");
    }
  });
}

// =========================
// EDIT BUTTON
// =========================
$(document).on("click", ".edit-btn", function (e) {
  e.stopPropagation();
  const id = $(this).data("id");
  window.location.href = `promodizers.php?edit=${id}`;
});

// =========================
// ADD PROMODIZER BUTTON
// =========================
$(document).on("click", ".add-promodizer-btn", function (e) {
  e.stopPropagation();
  window.location.href = "promodizers.php?add=1";
});
