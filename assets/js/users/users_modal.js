function formatMDY(dateStr) {
  const date = new Date(dateStr);
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const year = date.getFullYear();
  return `${month}/${day}/${year}`;
}

$(document).on("click", ".view-user", function () {
  const username = $(this).data("username");

  // Store for save
  $("#v_username").val(username);

  $.ajax({
    url: "functions/get_user.php",
    type: "POST",
    data: { username },
    dataType: "json",
    success: function (data) {
      const roleLabels = {
        admin: "ADMIN",
        super_admin: "SUPER ADMIN",
        staff: "STAFF",
        supervisor: "SUPERVISOR",
      };

      $("#v_first_name").val(data.first_name);
      $("#v_last_name").val(data.last_name);
      $("#v_position").val(data.position);
      $("#v_role").val(roleLabels[data.role] ?? data.role);
      $("#v_created_at").val(formatMDY(data.created_at));
      $("#v_updated_at").val(formatMDY(data.updated_at));

      // Assigned branch codes
      const assigned = data.branch
        ? data.branch.split(",").map((c) => c.trim())
        : [];

      // All branches: { code: name }
      const allBranches = data.branch_names ?? {};

      const isStaff = data.role === "staff";

      const branchHtml = Object.entries(allBranches)
        .map(([code, name]) => {
          const checked = assigned.includes(code) ? "checked" : "";
          const disabled = !isStaff ? "disabled" : "";

          return `
      <div class="form-check" style="margin: 2px 4px;">
          <input class="form-check-input branch-checkbox"
                 type="checkbox"
                 value="${code}"
                 id="branch_${code}"
                 ${checked}
                 ${disabled}>
          <label class="form-check-label" for="branch_${code}">
              ${name}
          </label>
      </div>`;
        })
        .join("");

      $("#v_branch").html(
        branchHtml ||
          '<span class="text-muted" style="padding: 4px;">No branches available</span>',
      );

      $("#userViewModal").modal("show");
    },
  });
});

// Save branch assignments
$(document).on("click", "#saveBranchBtn", function () {
  const username = $("#v_username").val();
  const selected = $(".branch-checkbox:checked")
    .map((_, el) => el.value)
    .get()
    .join(",");

  Swal.fire({
    icon: "question",
    title: "Update Branches?",
    text: `Save branch changes for "${username}"?`,
    showCancelButton: true,
    confirmButtonText: "Save",
    cancelButtonText: "Cancel",
    confirmButtonColor: "#2d68c4",
    cancelButtonColor: "#6c757d",
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.ajax({
      url: "functions/update_user_branches.php",
      type: "POST",
      data: { username, branches: selected },
      dataType: "json",
      success: function (res) {
        if (res.success) {
          Swal.fire({
            icon: "success",
            title: "Saved!",
            text: "Branches updated successfully.",
            confirmButtonColor: "#2d68c4",
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: res.message ?? "Could not save branches.",
            confirmButtonColor: "#2d68c4",
          });
        }
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "Request Failed",
          text: "Something went wrong. Please try again.",
          confirmButtonColor: "#2d68c4",
        });
      },
    });
  });
});
