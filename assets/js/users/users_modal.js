/* ───────────────────────────────────────────
   BRANCH HELPERS
─────────────────────────────────────────── */
function sortBranches() {
  const leftPane = document.getElementById("v_branch_left"); // Branches
  const rightPane = document.getElementById("v_branch_right"); // Selected
  if (!leftPane || !rightPane) return;

  const allItems = [
    ...leftPane.querySelectorAll(".branch-item"),
    ...rightPane.querySelectorAll(".branch-item"),
  ];

  // distribute between panes
  allItems.forEach((el) => {
    const checked = el.querySelector(".branch-checkbox").checked;
    (checked ? rightPane : leftPane).appendChild(el);
  });

  // FIX: restore original order in Branches pane
  [...leftPane.querySelectorAll(".branch-item")]
    .sort(
      (a, b) =>
        (parseInt(a.dataset.index) || 0) - (parseInt(b.dataset.index) || 0),
    )
    .forEach((el) => leftPane.appendChild(el));
}

/* ───────────────────────────────────────────
   COUNTER
─────────────────────────────────────────── */
function updateBranchCounter() {
  const $modal = $("#userViewModal");
  const count = $modal.find(".branch-checkbox:checked").length;
  $modal.find("#branchCounter").text(`Selected: ${count}`);
}

/* ───────────────────────────────────────────
   SESSION ROLE CHECK
─────────────────────────────────────────── */
function isPrivileged(requiredRole) {
  const role = (typeof SESSION_ROLE !== "undefined" ? SESSION_ROLE : "")
    .trim()
    .toLowerCase();

  if (requiredRole) {
    return role === requiredRole.trim().toLowerCase();
  }

  return role === "admin" || role === "super_admin";
}

/* ───────────────────────────────────────────
   CHANGE DETECTION HELPER
─────────────────────────────────────────── */
function refreshSaveBtn() {
  const $modal = $("#userViewModal");

  const origBranches = $modal.data("originalBranches");
  const current = new Set(
    $modal
      .find(".branch-checkbox:checked")
      .map((_, el) => el.value.trim())
      .get(),
  );
  const branchChanged =
    !!origBranches &&
    (current.size !== origBranches.size ||
      [...current].some((v) => !origBranches.has(v)));

  const origPos = $modal.data("originalPosition");
  const origRole = $modal.data("originalRole");
  const profileChanged =
    origPos !== undefined &&
    ($("#v_position").val().trim() !== origPos ||
      $("#v_role").val() !== origRole);

  $("#saveChangesBtn").prop("disabled", !branchChanged && !profileChanged);
}

/* ───────────────────────────────────────────
   ROLE CHANGE  →  branch access
─────────────────────────────────────────── */
$(document).on("change", "#v_role", function () {
  const becameStaff = $(this).val() === "staff";

  $("#branchSearch").prop("disabled", !becameStaff).val("");

  if (becameStaff) {
    $("#userViewModal .branch-checkbox").prop("disabled", false);
  } else {
    $("#userViewModal .branch-checkbox").prop({
      checked: false,
      disabled: true,
    });
    sortBranches();
    updateBranchCounter();
  }

  refreshSaveBtn();
});

/* ───────────────────────────────────────────
   SEARCH — filters both panes
─────────────────────────────────────────── */
$(document).on("input", "#branchSearch", function () {
  const search = $(this).val().trim().toUpperCase();

  $("#userViewModal .branch-item").each(function () {
    const text = $(this).find("label").text().trim().toUpperCase();
    $(this).toggle(search === "" || text.includes(search));
  });
});

/* ───────────────────────────────────────────
   CHECKBOX / FIELD CHANGES
─────────────────────────────────────────── */
$(document).on("change", "#userViewModal .branch-checkbox", function () {
  sortBranches();
  updateBranchCounter();
  refreshSaveBtn();
});

$(document).on("input change", "#v_position, #v_role", function () {
  refreshSaveBtn();
});

/* ───────────────────────────────────────────
   UTILITIES
─────────────────────────────────────────── */
function formatMDY(dateStr) {
  const date = new Date(dateStr);
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const year = date.getFullYear();
  return `${month}/${day}/${year}`;
}

/* ───────────────────────────────────────────
   VIEW USER
─────────────────────────────────────────── */
$(document).on("click", ".view-user", function () {
  const username = $(this).data("username");
  const isReadonly = $(this).hasClass("view-user-readonly");

  $.ajax({
    url: "functions/get_user.php",
    type: "POST",
    data: { username },
    dataType: "json",
    success: function (data) {
      const role = (data.role || "").trim().toLowerCase();
      const isStaff = role === "staff";
      const canEdit = isReadonly ? false : isPrivileged();
      const isSuperAdmin = isReadonly ? false : isPrivileged("super_admin");

      const assigned = data.branch
        ? data.branch.split(",").map((c) => c.trim())
        : [];
      const normalizedAssigned = assigned.map((v) => v.trim());
      const allBranches = data.branch_names ?? {};

      const roleLabels = {
        admin: "ADMIN",
        super_admin: "SUPER ADMIN",
        staff: "STAFF",
        supervisor: "SUPERVISOR",
      };

      /* ── basic fields ── */
      $("#v_username").val(username);
      $("#v_first_name").val(data.first_name);
      $("#v_last_name").val(data.last_name);
      $("#v_created_at").val(formatMDY(data.created_at));
      $("#v_updated_at").val(formatMDY(data.updated_at));
      $("#v_position").val(data.position).prop("readonly", !canEdit);

      /* ── role ── */
      if (canEdit) {
        if (isSuperAdmin) {
          $("#v_role_wrapper").html(
            `<select id="v_role" class="form-control">
               <option value="staff">STAFF</option>
               <option value="supervisor">SUPERVISOR</option>
               <option value="admin">ADMIN</option>
             </select>`,
          );
          $("#v_role").val(data.role);
        } else if (data.role === "admin") {
          $("#v_role_wrapper").html(
            `<input type="text" id="v_role" class="form-control" readonly>`,
          );
          $("#v_role").val(roleLabels["admin"]);
        } else {
          $("#v_role_wrapper").html(
            `<select id="v_role" class="form-control">
               <option value="staff">STAFF</option>
               <option value="supervisor">SUPERVISOR</option>
             </select>`,
          );
          $("#v_role").val(data.role);
        }
      } else {
        $("#v_role_wrapper").html(
          `<input type="text" id="v_role" class="form-control" readonly>`,
        );
        $("#v_role").val(roleLabels[data.role] ?? data.role);
      }

      /* ── buttons ── */
      $("#resetPasswordBtn").toggle(canEdit);
      $("#saveChangesBtn").toggle(!isReadonly);

      /* ── search bar ── */
      const $modal = $("#userViewModal");
      $modal
        .find("#branchSearch")
        .prop("disabled", !isStaff || isReadonly)
        .val("");

      /* ── build two-pane branch layout ── */
      const leftItems = [];
      const rightItems = [];

      Object.entries(allBranches).forEach(([code, name], index) => {
        const checked = normalizedAssigned.includes(String(code).trim());
        const disabled = !isStaff || isReadonly;

        const item = `
          <div class="branch-item" data-index="${index}" style="margin:2px 0;">
            <input class="form-check-input branch-checkbox"
                   type="checkbox"
                   value="${code}"
                   id="v_branch_${code}"
                   ${checked ? "checked" : ""}
                   ${disabled ? "disabled" : ""}>
            <label class="form-check-label" for="v_branch_${code}">${name}</label>
          </div>`;

        (checked ? rightItems : leftItems).push(item);
      });

      $("#v_branch").html(
        Object.keys(allBranches).length
          ? `<div class="branch-col">
              <div class="branch-col-header">Branches</div>
              <div id="v_branch_left" class="branch-pane">${leftItems.join("")}</div>
            </div>
            <div class="branch-col-divider"></div>
            <div class="branch-col">
              <div class="branch-col-header">Selected</div>
              <div id="v_branch_right" class="branch-pane">${rightItems.join("")}</div>
            </div>`
          : '<span class="text-muted">No branches available</span>',
      );

      $modal.data("originalBranches", new Set(normalizedAssigned));
      $modal.data("originalPosition", (data.position || "").trim());
      $modal.data("originalRole", data.role);
      $("#saveChangesBtn").prop("disabled", true);

      setTimeout(updateBranchCounter, 0);
      $modal.modal("show");
    },
  });
});

/* ───────────────────────────────────────────
   SAVE CHANGES
─────────────────────────────────────────── */
$(document).on("click", "#saveChangesBtn", function () {
  const $modal = $("#userViewModal");
  const username = $("#v_username").val();
  const position = $("#v_position").val().trim();
  const role = $("#v_role").val();

  const origBranches = $modal.data("originalBranches");
  const current = new Set(
    $modal
      .find(".branch-checkbox:checked")
      .map((_, el) => el.value.trim())
      .get(),
  );
  const branchChanged =
    !!origBranches &&
    (current.size !== origBranches.size ||
      [...current].some((v) => !origBranches.has(v)));

  const origPos = $modal.data("originalPosition");
  const origRole = $modal.data("originalRole");
  const profileChanged =
    origPos !== undefined &&
    isPrivileged() &&
    (position !== origPos || role !== origRole);

  if (!branchChanged && !profileChanged) return;

  if (profileChanged && !position) {
    Swal.fire("Validation", "Position cannot be empty.", "warning");
    return;
  }

  Swal.fire({
    icon: "question",
    title: "Save Changes?",
    showCancelButton: true,
  }).then((result) => {
    if (!result.isConfirmed) return;

    const requests = [];

    if (profileChanged) {
      requests.push(
        $.ajax({
          url: "functions/update_user_profile.php",
          type: "POST",
          data: { username, position, role },
          dataType: "json",
        }),
      );
    }

    if (branchChanged) {
      requests.push(
        $.ajax({
          url: "functions/update_user_branches.php",
          type: "POST",
          data: { username, branches: [...current].join(",") },
          dataType: "json",
        }),
      );
    }

    Promise.all(requests)
      .then((results) => {
        const failed = results.find((r) => !r.success);
        if (failed) {
          Swal.fire("Error", failed.message || "An error occurred.", "error");
        } else {
          Swal.fire("Successfully saved!", "", "success").then(() =>
            location.reload(),
          );
        }
      })
      .catch(() => Swal.fire("Error", "Request failed.", "error"));
  });
});

/* ───────────────────────────────────────────
   RESET PASSWORD
─────────────────────────────────────────── */
$(document).on("click", "#resetPasswordBtn", function () {
  if (!isPrivileged()) return;

  const username = $("#v_username").val();
  const newPassword = "Password123";

  Swal.fire({
    icon: "warning",
    title: "Reset Password?",
    html: `This will reset the password for <strong>${username}</strong> to:<br><br>
           <code style="font-size:1.1rem;">${newPassword}</code>`,
    showCancelButton: true,
    confirmButtonText: "Yes, Reset",
    confirmButtonColor: "#f0ad4e",
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.ajax({
      url: "functions/reset_user_password.php",
      type: "POST",
      data: { username, password: newPassword },
      dataType: "json",
      success: function (res) {
        if (res.success) {
          Swal.fire({
            icon: "success",
            title: "Password Reset!",
            html: `Password for <strong>${username}</strong> has been reset to:<br><br>
                   <code style="font-size:1.1rem;">${newPassword}</code>`,
          });
        } else {
          Swal.fire("Error", res.message, "error");
        }
      },
      error: function () {
        Swal.fire("Error", "Request failed.", "error");
      },
    });
  });
});

/* ───────────────────────────────────────────
   INIT
─────────────────────────────────────────── */
$(document).ready(function () {
  updateBranchCounter();
});
