/* ───────────────────────────────────────────
   AUDIT ROLES
─────────────────────────────────────────── */
const VIEW_MODAL_AUDIT_ROLES = ["audit_manager", "audit_supervisor", "audit_staff"];

function isAuditRole(role) {
  return VIEW_MODAL_AUDIT_ROLES.includes((role || "").trim().toLowerCase());
}

function isRegionalRole(role) {
  return (role || "").trim().toLowerCase() === "regional_manager";
}

function setBranchSectionVisible(visible) {
  $("#v_branchSectionWrapper").toggle(!!visible);
}

function setRegionSectionVisible(visible) {
  $("#v_regionSectionWrapper").toggle(!!visible);
}

/* ───────────────────────────────────────────
   BRANCH HELPERS
─────────────────────────────────────────── */
function sortBranches() {
  const leftPane = document.getElementById("v_branch_left");
  const rightPane = document.getElementById("v_branch_right");
  if (!leftPane || !rightPane) return;

  const allItems = [
    ...leftPane.querySelectorAll(".branch-item"),
    ...rightPane.querySelectorAll(".branch-item"),
  ];

  allItems.forEach((el) => {
    const checked = el.querySelector(".branch-checkbox").checked;
    (checked ? rightPane : leftPane).appendChild(el);
  });

  [...leftPane.querySelectorAll(".branch-item")]
    .sort((a, b) => (parseInt(a.dataset.index) || 0) - (parseInt(b.dataset.index) || 0))
    .forEach((el) => leftPane.appendChild(el));
}

function updateBranchCounter() {
  const $modal = $("#userViewModal");
  const count = $modal.find(".branch-checkbox:checked").length;
  $modal.find("#branchCounter").text(`Selected: ${count}`);
}

/* ───────────────────────────────────────────
   REGION HELPERS (mirror of branch helpers)
─────────────────────────────────────────── */
function sortRegions() {
  const leftPane = document.getElementById("v_region_left");
  const rightPane = document.getElementById("v_region_right");
  if (!leftPane || !rightPane) return;

  const allItems = [
    ...leftPane.querySelectorAll(".region-item"),
    ...rightPane.querySelectorAll(".region-item"),
  ];

  allItems.forEach((el) => {
    const checked = el.querySelector(".region-checkbox").checked;
    (checked ? rightPane : leftPane).appendChild(el);
  });

  [...leftPane.querySelectorAll(".region-item")]
    .sort((a, b) => (parseInt(a.dataset.index) || 0) - (parseInt(b.dataset.index) || 0))
    .forEach((el) => leftPane.appendChild(el));
}

function updateRegionCounter() {
  const $modal = $("#userViewModal");
  const count = $modal.find(".region-checkbox:checked").length;
  $modal.find("#regionCounter").text(`Selected: ${count}`);
}

/* ───────────────────────────────────────────
   ROLE → BRANCH / REGION SELECTION MODE
─────────────────────────────────────────── */
function branchSelectionAllowed(role) {
  return role === "staff" || role === "branch_manager";
}

function viewModalIsBranchManagerRole() {
  return $("#v_role").val() === "branch_manager";
}

function viewModalIsRegionalRole() {
  return $("#v_role").val() === "regional_manager";
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

  // "edit-capable" tiers: HR admins/super_admin, plus their audit-side
  // counterparts (audit_manager/audit_supervisor) editing within their
  // own scope. Actual authorization is still enforced server-side by
  // get_user.php / update_user_profile.php / update_user_branches.php.
  return ["admin", "super_admin", "audit_manager", "audit_supervisor"].includes(
    role,
  );
}

/* ───────────────────────────────────────────
   USERNAME GENERATION  (mirrors backend logic)
   "first last" lowercase, space-separated
─────────────────────────────────────────── */
function generateUsername(first, last) {
  return [first, last]
    .map((v) => (v || "").trim())
    .filter(Boolean)
    .join(" ")
    .toUpperCase();
}

function syncUsernamePreview() {
  const first = $("#v_first_name").val();
  const last = $("#v_last_name").val();
  $("#v_username").val(generateUsername(first, last));
}

/* ───────────────────────────────────────────
   CHANGE DETECTION HELPER
─────────────────────────────────────────── */
function refreshSaveBtn() {
  const $modal = $("#userViewModal");

  const origBranches = $modal.data("originalBranches");
  const currentBranches = new Set(
    $modal.find(".branch-checkbox:checked").map((_, el) => el.value.trim()).get(),
  );
  const branchChanged =
    !!origBranches &&
    (currentBranches.size !== origBranches.size ||
      [...currentBranches].some((v) => !origBranches.has(v)));

  const origRegions = $modal.data("originalRegions");
  const currentRegions = new Set(
    $modal.find(".region-checkbox:checked").map((_, el) => el.value.trim()).get(),
  );
  const regionChanged =
    !!origRegions &&
    (currentRegions.size !== origRegions.size ||
      [...currentRegions].some((v) => !origRegions.has(v)));

  const origPos = $modal.data("originalPosition");
  const origRole = $modal.data("originalRole");
  const origFirst = $modal.data("originalFirstName");
  const origMiddle = $modal.data("originalMiddleName");
  const origLast = $modal.data("originalLastName");
  const origSuffix = $modal.data("originalSuffix");

  const profileChanged =
    origPos !== undefined &&
    ($("#v_position").val().trim() !== origPos ||
      $("#v_role").val() !== origRole ||
      $("#v_first_name").val().trim() !== origFirst ||
      $("#v_middle_name").val().trim() !== origMiddle ||
      $("#v_last_name").val().trim() !== origLast ||
      $("#v_suffix").val().trim() !== origSuffix);

  $("#saveChangesBtn").prop("disabled", !branchChanged && !regionChanged && !profileChanged);
}

/* ───────────────────────────────────────────
   ROLE CHANGE → branch / region access
─────────────────────────────────────────── */
$(document).on("change", "#v_role", function () {
  const role = $(this).val();
  const allowedBranch = branchSelectionAllowed(role);
  const allowedRegion = isRegionalRole(role);

  setBranchSectionVisible(!isAuditRole(role) && !isRegionalRole(role));
  setRegionSectionVisible(allowedRegion);

  $("#branchSearch").prop("disabled", !allowedBranch).val("");
  $("#regionSearch").prop("disabled", !allowedRegion).val("");
  $("#userViewModal .branch-item, #userViewModal .region-item").show();

  if (allowedBranch) {
    $("#userViewModal .branch-checkbox").prop("disabled", false);
    if (role === "branch_manager") {
      $("#userViewModal .branch-checkbox:checked").each(function (i) {
        if (i > 0) $(this).prop("checked", false);
      });
    }
  } else {
    $("#userViewModal .branch-checkbox").prop({ checked: false, disabled: true });
  }

  if (allowedRegion) {
    $("#userViewModal .region-checkbox").prop("disabled", false);
    // regional_manager = single region only, same radio behavior as branch_manager
    $("#userViewModal .region-checkbox:checked").each(function (i) {
      if (i > 0) $(this).prop("checked", false);
    });
  } else {
    $("#userViewModal .region-checkbox").prop({ checked: false, disabled: true });
  }

  sortBranches();
  sortRegions();
  updateBranchCounter();
  updateRegionCounter();
  refreshSaveBtn();
});

/* ───────────────────────────────────────────
   SEARCH
─────────────────────────────────────────── */
$(document).on("input", "#branchSearch", function () {
  const search = $(this).val().trim().toUpperCase();
  $("#userViewModal .branch-item").each(function () {
    const text = $(this).find("label").text().trim().toUpperCase();
    $(this).toggle(search === "" || text.includes(search));
  });
});

$(document).on("input", "#regionSearch", function () {
  const search = $(this).val().trim().toUpperCase();
  $("#userViewModal .region-item").each(function () {
    const text = $(this).find("label").text().trim().toUpperCase();
    $(this).toggle(search === "" || text.includes(search));
  });
});

/* ───────────────────────────────────────────
   CHECKBOX / FIELD CHANGES
─────────────────────────────────────────── */
$(document).on("change", "#userViewModal .branch-checkbox", function () {
  const changedEl = this;
  if (viewModalIsBranchManagerRole() && changedEl.checked) {
    $("#userViewModal .branch-checkbox:checked").each(function () {
      if (this !== changedEl) $(this).prop("checked", false);
    });
  }
  sortBranches();
  updateBranchCounter();
  refreshSaveBtn();
});

$(document).on("change", "#userViewModal .region-checkbox", function () {
  const changedEl = this;
  if (viewModalIsRegionalRole() && changedEl.checked) {
    $("#userViewModal .region-checkbox:checked").each(function () {
      if (this !== changedEl) $(this).prop("checked", false);
    });
  }
  sortRegions();
  updateRegionCounter();
  refreshSaveBtn();
});

$(document).on("input change", "#v_position, #v_role", function () {
  refreshSaveBtn();
});

// live username preview while typing first/last name (WHILE typing)
$(document).on("input", "#v_first_name, #v_last_name", function () {
  syncUsernamePreview();
  refreshSaveBtn();
});

$(document).on("input", "#v_middle_name, #v_suffix", function () {
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
  const id = $(this).data("id");
  const isReadonly = $(this).hasClass("view-user-readonly");

  $.ajax({
    url: "functions/get_user.php",
    type: "POST",
    data: { id },
    dataType: "json",
    success: function (data) {
      if (data.error) {
        Swal.fire("Error", data.error, "error");
        return;
      }

      const username = data.username;
      const role = (data.role || "").trim().toLowerCase();
      const isAuditUser = isAuditRole(role);
      const isRegionUser = isRegionalRole(role);
      const allowsBranchSelection = branchSelectionAllowed(role);
      const canEdit = isReadonly ? false : isPrivileged();
      const isSuperAdmin = isReadonly ? false : isPrivileged("super_admin");
      const sessionRole = (
        typeof SESSION_ROLE !== "undefined" ? SESSION_ROLE : ""
      )
        .trim()
        .toLowerCase();

      const assignedBranches = data.branch
        ? data.branch.split(",").map((c) => c.trim())
        : [];
      const normalizedAssignedBranches = assignedBranches.map((v) => v.trim());
      const allBranches = data.branch_names ?? {};

      // expects get_user.php to also return data.region (comma list, though
      // regional_manager only ever has one) and data.region_names (code -> name map),
      // same shape as branch/branch_names
      const assignedRegions = data.region
        ? data.region.split(",").map((c) => c.trim())
        : [];
      const normalizedAssignedRegions = assignedRegions.map((v) => v.trim());
      const allRegions = data.region_names ?? {};

      const roleLabels = {
        admin: "ADMIN",
        super_admin: "SUPER ADMIN",
        staff: "STAFF",
        supervisor: "SUPERVISOR",
        branch_manager: "BRANCH",
        regional_manager: "REGIONAL",
        audit_manager: "AUDIT MANAGER",
        audit_supervisor: "AUDIT SUPERVISOR",
        audit_staff: "AUDIT STAFF",
      };

      /* ── basic fields ── */
      $("#v_id").val(data.id);
      $("#v_username").val(username);

      $("#v_first_name").val(data.first_name).prop("readonly", !canEdit);
      $("#v_middle_name").val(data.middle_name).prop("readonly", !canEdit);
      $("#v_last_name").val(data.last_name).prop("readonly", !canEdit);
      $("#v_suffix").val(data.suffix).prop("readonly", !canEdit);

      $("#v_created_at").val(formatMDY(data.created_at));
      $("#v_updated_at").val(formatMDY(data.updated_at));
      $("#v_position").val(data.position).prop("readonly", !canEdit);

      /* ── branch/region section visibility ── */
      setBranchSectionVisible(!isAuditUser && !isRegionUser);
      setRegionSectionVisible(isRegionUser);

      /* ── role ── */
      if (canEdit) {
        if (isAuditUser) {
          if (isSuperAdmin) {
            $("#v_role_wrapper").html(
              `<select id="v_role" class="form-control">
                 <option value="audit_staff">AUDIT STAFF</option>
                 <option value="audit_supervisor">AUDIT SUPERVISOR</option>
                 <option value="audit_manager">AUDIT MANAGER</option>
               </select>`,
            );
            $("#v_role").val(data.role);
          } else {
            // audit_manager can move someone between audit_supervisor/audit_staff;
            // audit_supervisor can only keep/set audit_staff
            const assignableAuditRoles =
              sessionRole === "audit_manager"
                ? ["audit_staff", "audit_supervisor"]
                : ["audit_staff"];

            if (assignableAuditRoles.includes(data.role)) {
              const options = assignableAuditRoles
                .map((r) => `<option value="${r}">${roleLabels[r]}</option>`)
                .join("");
              $("#v_role_wrapper").html(
                `<select id="v_role" class="form-control">${options}</select>`,
              );
              $("#v_role").val(data.role);
            } else {
              // outside this editor's assignable range — show read-only
              // rather than a select that can't represent the current value
              $("#v_role_wrapper").html(
                `<input type="text" id="v_role" class="form-control" readonly>`,
              );
              $("#v_role").val(roleLabels[data.role] ?? data.role);
            }
          }
        } else if (isSuperAdmin) {
          $("#v_role_wrapper").html(
            `<select id="v_role" class="form-control">
               <option value="staff">STAFF</option>
               <option value="supervisor">SUPERVISOR</option>
               <option value="branch_manager">BRANCH</option>
               <option value="regional_manager">REGIONAL</option>
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
               <option value="branch_manager">BRANCH</option>
               <option value="regional_manager">REGIONAL</option>
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
        .prop("disabled", !allowsBranchSelection || isReadonly)
        .val("");
      $modal
        .find("#regionSearch")
        .prop("disabled", !isRegionUser || isReadonly)
        .val("");

      /* ── build two-pane branch layout ── */
      const branchLeftItems = [];
      const branchRightItems = [];

      Object.entries(allBranches).forEach(([code, name], index) => {
        const checked = normalizedAssignedBranches.includes(String(code).trim());
        const disabled = !allowsBranchSelection || isReadonly;

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

        (checked ? branchRightItems : branchLeftItems).push(item);
      });

      $("#v_branch").html(
        Object.keys(allBranches).length
          ? `<div class="branch-col">
              <div class="branch-col-header">Branches</div>
              <div id="v_branch_left" class="branch-pane">${branchLeftItems.join("")}</div>
            </div>
            <div class="branch-col-divider"></div>
            <div class="branch-col">
              <div class="branch-col-header">Selected</div>
              <div id="v_branch_right" class="branch-pane">${branchRightItems.join("")}</div>
            </div>`
          : '<span class="text-muted">No branches available</span>',
      );

      /* ── build two-pane region layout ── */
      const regionLeftItems = [];
      const regionRightItems = [];

      Object.entries(allRegions).forEach(([code, name], index) => {
        const checked = normalizedAssignedRegions.includes(String(code).trim());
        const disabled = !isRegionUser || isReadonly;

        const item = `
          <div class="region-item" data-index="${index}" style="margin:2px 0;">
            <input class="form-check-input region-checkbox"
                   type="checkbox"
                   value="${code}"
                   id="v_region_${code}"
                   ${checked ? "checked" : ""}
                   ${disabled ? "disabled" : ""}>
            <label class="form-check-label" for="v_region_${code}">${name}</label>
          </div>`;

        (checked ? regionRightItems : regionLeftItems).push(item);
      });

      $("#v_region").html(
        Object.keys(allRegions).length
          ? `<div class="branch-col">
              <div class="branch-col-header">Regions</div>
              <div id="v_region_left" class="branch-pane">${regionLeftItems.join("")}</div>
            </div>
            <div class="branch-col-divider"></div>
            <div class="branch-col">
              <div class="branch-col-header">Selected</div>
              <div id="v_region_right" class="branch-pane">${regionRightItems.join("")}</div>
            </div>`
          : '<span class="text-muted">No regions available</span>',
      );

      $modal.data("originalBranches", new Set(normalizedAssignedBranches));
      $modal.data("originalRegions", new Set(normalizedAssignedRegions));
      $modal.data("originalPosition", (data.position || "").trim());
      $modal.data("originalRole", data.role);
      $modal.data("originalFirstName", (data.first_name || "").trim());
      $modal.data("originalMiddleName", (data.middle_name || "").trim());
      $modal.data("originalLastName", (data.last_name || "").trim());
      $modal.data("originalSuffix", (data.suffix || "").trim());
      $("#saveChangesBtn").prop("disabled", true);

      setTimeout(() => {
        updateBranchCounter();
        updateRegionCounter();
      }, 0);
      $modal.modal("show");
    },
    error: function () {
      Swal.fire("Error", "Failed to load user.", "error");
    },
  });
});

/* ───────────────────────────────────────────
   SAVE CHANGES
─────────────────────────────────────────── */
$(document).on("click", "#saveChangesBtn", function () {
  const $modal = $("#userViewModal");
  const id = $("#v_id").val();
  const position = $("#v_position").val().trim();
  const role = $("#v_role").val();
  const firstName = $("#v_first_name").val().trim();
  const middleName = $("#v_middle_name").val().trim();
  const lastName = $("#v_last_name").val().trim();
  const suffix = $("#v_suffix").val().trim();
  const username = generateUsername(firstName, lastName);

  const origBranches = $modal.data("originalBranches");
  const currentBranches = new Set(
    $modal.find(".branch-checkbox:checked").map((_, el) => el.value.trim()).get(),
  );

  const origRegions = $modal.data("originalRegions");
  const currentRegions = new Set(
    $modal.find(".region-checkbox:checked").map((_, el) => el.value.trim()).get(),
  );

  if (role === "branch_manager" && currentBranches.size !== 1) {
    Swal.fire(
      "Validation",
      "Please select exactly one branch for a Branch Manager.",
      "warning",
    );
    return;
  }

  if (role === "regional_manager" && currentRegions.size !== 1) {
    Swal.fire(
      "Validation",
      "Please select exactly one region for a Regional Manager.",
      "warning",
    );
    return;
  }

  const branchChanged =
    !!origBranches &&
    (currentBranches.size !== origBranches.size ||
      [...currentBranches].some((v) => !origBranches.has(v)));

  const regionChanged =
    !!origRegions &&
    (currentRegions.size !== origRegions.size ||
      [...currentRegions].some((v) => !origRegions.has(v)));

  const origPos = $modal.data("originalPosition");
  const origRole = $modal.data("originalRole");
  const origFirst = $modal.data("originalFirstName");
  const origMiddle = $modal.data("originalMiddleName");
  const origLast = $modal.data("originalLastName");
  const origSuffix = $modal.data("originalSuffix");

  const profileChanged =
    origPos !== undefined &&
    isPrivileged() &&
    (position !== origPos ||
      role !== origRole ||
      firstName !== origFirst ||
      middleName !== origMiddle ||
      lastName !== origLast ||
      suffix !== origSuffix);

  if (!branchChanged && !regionChanged && !profileChanged) return;

  if (profileChanged && !position) {
    Swal.fire("Validation", "Position cannot be empty.", "warning");
    return;
  }

  if (profileChanged && (!firstName || !lastName)) {
    Swal.fire(
      "Validation",
      "First name and last name cannot be empty.",
      "warning",
    );
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
          data: {
            id,
            position,
            role,
            first_name: firstName,
            middle_name: middleName,
            last_name: lastName,
            suffix,
          },
          dataType: "json",
        }),
      );
    }

    if (branchChanged) {
      requests.push(
        $.ajax({
          url: "functions/update_user_branches.php",
          type: "POST",
          data: { id, username, branches: [...currentBranches].join(",") },
          dataType: "json",
        }),
      );
    }

    // expects a new endpoint mirroring update_user_branches.php for the region column
    if (regionChanged) {
      requests.push(
        $.ajax({
          url: "functions/update_user_regions.php",
          type: "POST",
          data: { id, username, regions: [...currentRegions].join(",") },
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

  const id = $("#v_id").val();
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
      data: { id, username, password: newPassword },
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
  updateRegionCounter();
});