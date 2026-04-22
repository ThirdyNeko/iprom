let assignmentModalDisabled = false;
let currentAssigned = 0;

// =========================
// STATUS BADGE
// =========================
function getStatusBadge(required, assigned) {
  let shortage = required - assigned;

  if (assigned === 0) {
    return `<span class="badge bg-danger">INACTIVE</span>`;
  } else if (shortage > 0) {
    return `<span class="badge bg-warning">VACANT: ${shortage}</span>`;
  } else {
    return `<span class="badge bg-success">ACTIVE</span>`;
  }
}

// =========================
// CLICK ROW → OPEN MODAL
// =========================
$(document).on("click", "#assignmentTable tbody tr", function () {
  const row = $(this);

  const branch = row.data("branch");
  const brand = row.data("brand");

  if (!branch || !brand) return;

  const required = parseInt(row.data("required")) || 0;
  const assigned = parseInt(row.data("assigned")) || 0;
  const updated = row.data("updated") || null;

  currentAssigned = assigned;

  $("#assignmentModal").data("branch", branch);
  $("#assignmentModal").data("brand", brand);

  $("#modalBranch").text(branch);
  $("#modalBrand").text(brand);
  $("#modalRequired").val(required);
  $("#modalStatus").html(getStatusBadge(required, assigned));

  $("#modalAssignedList").html('<small class="text-muted">Loading...</small>');

  const modalEl = document.getElementById("assignmentModal");
  bootstrap.Modal.getOrCreateInstance(modalEl).show();

  fetch("functions/get_assigned_promodizers.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ branch, brand }),
  })
    .then((res) => res.json())
    .then((res) => {
      if (!res || res.status !== "success") {
        assignmentModalDisabled = true;

        $("#modalAssignedList").html(
          '<div class="alert alert-danger mb-0">Failed to load assignments.</div>',
        );

        $("#saveRequiredBtn").prop("disabled", true);
        return;
      }

      assignmentModalDisabled = false;
      currentAssigned = res.data.length;

      let html = "";

      if (currentAssigned === 0) {
        html = '<small class="text-muted">No assigned employees</small>';
      } else {
        html = '<ul class="list-group list-group-flush">';

        res.data.forEach((emp) => {
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

      const requiredVal = parseInt($("#modalRequired").val()) || 0;

      if (currentAssigned < requiredVal) {
        html += `
          <div class="mt-2 text-center">
            <a href="promodizers.php?status=inactive" class="btn btn-sm btn-primary">
              + Add Promodizer
            </a>
          </div>
        `;
      }

      $("#modalAssignedList").html(html);
      $("#modalStatus").html(getStatusBadge(requiredVal, currentAssigned));
    })
    .catch((err) => {
      console.error(err);

      assignmentModalDisabled = true;

      $("#modalAssignedList").html(
        '<div class="alert alert-danger mb-0">Service unavailable.</div>',
      );
    });

  $("#modalUpdated").text(
    updated
      ? new Date(updated.replace(" ", "T")).toLocaleDateString("en-CA")
      : "-",
  );

  $("#modalUpdatedBy").text(row.data("updated-by") || "-");
});

// =========================
// SAVE REQUIRED (FIXED)
// =========================
document
  .getElementById("saveRequiredBtn")
  .addEventListener("click", async () => {
    const modal = $("#assignmentModal");
    const branch = modal.data("branch");
    const brand = modal.data("brand");

    const required = Number($("#modalRequired").val());

    if (!branch || !brand) {
      return Swal.fire({
        icon: "error",
        title: "Missing Data",
        text: "No assignment selected.",
      });
    }

    if (assignmentModalDisabled) {
      return Swal.fire({
        icon: "error",
        title: "Unavailable",
        text: "Cannot update right now.",
      });
    }

    if (isNaN(required) || required < 0) {
      return Swal.fire({
        icon: "error",
        title: "Invalid Input",
        text: "Required must be a valid number.",
      });
    }

    // HARD RULE
    if (required < currentAssigned) {
      return Swal.fire({
        icon: "error",
        title: "Invalid Update",
        text: `Required (${required}) cannot be less than Assigned (${currentAssigned}). Please reassign or remove promodizers first.`,
      });
    }

    // 🚨 SPECIAL WARNING FOR ZERO
    if (required === 0) {
      const dangerConfirm = await Swal.fire({
        icon: "warning",
        title: "WARNING: Full Pull-Out",
        text: "Setting required to 0 will pull out ALL promodizers for this branch & brand. Do you want to continue?",
        showCancelButton: true,
        confirmButtonText: "Yes, I understand",
        cancelButtonText: "Cancel",
        confirmButtonColor: "#d33",
      });

      if (!dangerConfirm.isConfirmed) return;
    }

    // NORMAL CONFIRM
    const confirm = await Swal.fire({
      icon: "warning",
      title: "Update Required?",
      text: `Change required count for ${branch} - ${brand}?`,
      showCancelButton: true,
      confirmButtonText: "Yes, Update",
    });

    if (!confirm.isConfirmed) return;

    try {
      const res = await fetch("functions/update_required.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ branch, brand, required }),
      });

      const result = await res.json();

      if (result.status === "success") {
        await Swal.fire({
          icon: "success",
          title: "Updated!",
          timer: 1200,
          showConfirmButton: false,
        });

        window.assignmentTable.ajax.reload(null, false);

        bootstrap.Modal.getInstance(
          document.getElementById("assignmentModal"),
        ).hide();
      } else {
        Swal.fire({
          icon: "error",
          title: "Update Failed",
          text: result.message || "Something went wrong.",
        });
      }
    } catch (err) {
      console.error(err);
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "Server error occurred.",
      });
    }
  });

// =========================
// EDIT BUTTON
// =========================
$(document).on("click", ".edit-btn", function (e) {
  e.stopPropagation();
  const id = $(this).data("id");
  window.location.href = `promodizers.php?edit=${id}`;
});
