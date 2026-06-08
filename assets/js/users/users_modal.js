/* ───────────────────────────────────────────
   BRANCH HELPERS
─────────────────────────────────────────── */
function sortBranches() {
  const container = $("#v_branch");
  const items = container.find(".branch-item").toArray();

  if (!items.length) return;

  const positions = new Map();
  items.forEach((el) => positions.set(el, el.getBoundingClientRect().top));

  items.sort((a, b) => {
    const aChecked = $(a).find(".branch-checkbox").prop("checked") ? 1 : 0;
    const bChecked = $(b).find(".branch-checkbox").prop("checked") ? 1 : 0;

    if (aChecked !== bChecked) return bChecked - aChecked;

    return ($(a).data("index") || 0) - ($(b).data("index") || 0);
  });

  items.forEach((el) => container[0].appendChild(el));

  items.forEach((el) => {
    const diff = positions.get(el) - el.getBoundingClientRect().top;

    if (diff) {
      el.style.transition = "none";
      el.style.transform = `translateY(${diff}px)`;

      requestAnimationFrame(() => {
        el.style.transition = "transform 250ms ease";
        el.style.transform = "translateY(0)";
      });
    }
  });
}

/* ───────────────────────────────────────────
   COUNTER
─────────────────────────────────────────── */
function updateBranchCounter() {
  const $modal = $("#userViewModal");
  const count = $modal.find("#v_branch .branch-checkbox:checked").length;

  console.log("Counter updating:", count);
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
   Re-evaluates both branch + profile state
   and flips #saveChangesBtn accordingly.
─────────────────────────────────────────── */
function refreshSaveBtn() {
  const $modal = $("#userViewModal");

  /* — branch drift — */
  const origBranches = $modal.data("originalBranches");
  const current = new Set(
    $("#v_branch .branch-checkbox:checked")
      .map((_, el) => el.value.trim())
      .get(),
  );
  const branchChanged =
    !!origBranches &&
    (current.size !== origBranches.size ||
      [...current].some((v) => !origBranches.has(v)));

  /* — profile drift — */
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

  /* search bar */
  $("#branchSearch").prop("disabled", !becameStaff).val("");

  if (becameStaff) {
    /* re-enable all checkboxes */
    $("#v_branch .branch-checkbox").prop("disabled", false);
  } else {
    /* clear + lock all checkboxes */
    $("#v_branch .branch-checkbox").prop({ checked: false, disabled: true });
    sortBranches();
    updateBranchCounter();
  }

  refreshSaveBtn();
});

/* ───────────────────────────────────────────
   SEARCH
─────────────────────────────────────────── */
$(document).on("input", "#branchSearch", function () {
  const search = $(this).val().trim().toUpperCase();

  $("#userViewModal #v_branch .branch-item").each(function () {
    const text = $(this).find("label").text().trim().toUpperCase();
    $(this).toggle(search === "" || text.includes(search));
  });
});

/* ───────────────────────────────────────────
   CHECKBOX / FIELD CHANGES  →  shared button
─────────────────────────────────────────── */
$(document).on("change", "#v_branch input[type='checkbox']", function () {
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

  $.ajax({
    url: "functions/get_user.php",
    type: "POST",
    data: { username },
    dataType: "json",
    success: function (data) {
      const role = (data.role || "").trim().toLowerCase();
      const isStaff = role === "staff";
      const canEdit = isPrivileged();
      const isSuperAdmin = isPrivileged("super_admin");

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

      /* ───── populate basic fields ───── */
      $("#v_username").val(username);
      $("#v_first_name").val(data.first_name);
      $("#v_last_name").val(data.last_name);
      $("#v_created_at").val(formatMDY(data.created_at));
      $("#v_updated_at").val(formatMDY(data.updated_at));

      /* ───── position ───── */
      $("#v_position").val(data.position).prop("readonly", !canEdit);

      /* ───── role ───── */
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
        } else {
          if (data.role === "admin") {
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
        }
      } else {
        $("#v_role_wrapper").html(
          `<input type="text" id="v_role" class="form-control" readonly>`,
        );
        $("#v_role").val(roleLabels[data.role] ?? data.role);
      }

      /* ───── Reset Password visibility ───── */
      $("#resetPasswordBtn").toggle(canEdit);

      /* ───── search + branch toggles ───── */
      const $modal = $("#userViewModal");
      $modal.find("#branchSearch").prop("disabled", !isStaff).val("");

      /* ───── build branches ───── */
      const branchHtml = Object.entries(allBranches)
        .map(([code, name], index) => {
          const checked = normalizedAssigned.includes(String(code).trim())
            ? "checked"
            : "";
          const disabled = !isStaff ? "disabled" : "";

          return `
            <div class="form-check branch-item"
                 data-index="${index}"
                 style="display:inline-block;width:25%;margin:2px 0;">
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
        branchHtml || '<span class="text-muted">No branches available</span>',
      );

      /* ───── ensure UI updates after render ───── */
      setTimeout(() => {
        sortBranches();
        updateBranchCounter();
      }, 0);

      /* ───── store originals + reset button ───── */
      $modal.data("originalBranches", new Set(normalizedAssigned));
      $modal.data("originalPosition", (data.position || "").trim());
      $modal.data("originalRole", data.role);

      $("#saveChangesBtn").prop("disabled", true);

      $modal.modal("show");
    },
  });
});

/* ───────────────────────────────────────────
   SAVE CHANGES  (profile + branches unified)
─────────────────────────────────────────── */
$(document).on("click", "#saveChangesBtn", function () {
  const $modal = $("#userViewModal");
  const username = $("#v_username").val();
  const position = $("#v_position").val().trim();
  const role = $("#v_role").val();

  /* — determine what drifted — */
  const origBranches = $modal.data("originalBranches");
  const current = new Set(
    $("#v_branch .branch-checkbox:checked")
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
    text: `Save changes for "${username}"?`,
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
          Swal.fire("Saved!", "Changes updated.", "success").then(() =>
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
