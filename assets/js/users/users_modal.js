$(document).on("click", ".view-user", function () {
  let username = $(this).data("username");

  $.ajax({
    url: "functions/get_user.php",
    type: "POST",
    data: { username: username },
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
      const branches = data.branch ? data.branch.split(",") : [];
      const branchNames = data.branch_names ?? {};

      const branchHtml = branches
        .map(
          (code) =>
            `<div class="form-check" style="margin: 2px 4px;">
                    <input class="form-check-input" type="checkbox" disabled checked>
                    <label class="form-check-label">
                        ${branchNames[code.trim()] ?? code.trim()}
                    </label>
                </div>`,
        )
        .join("");

      $("#v_branch").html(
        branchHtml ||
          '<span class="text-muted" style="padding: 4px;">None</span>',
      );
      $("#v_role").val(roleLabels[data.role] ?? data.role);
      function formatMDY(dateStr) {
        const date = new Date(dateStr);

        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        const year = date.getFullYear();

        return `${month}/${day}/${year}`;
      }

      $("#v_created_at").val(formatMDY(data.created_at));
      $("#v_updated_at").val(formatMDY(data.updated_at));

      $("#userViewModal").modal("show");
    },
  });
});
