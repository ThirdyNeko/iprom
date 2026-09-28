// assets/js/users/create_user.js

/* ───────────────────────────────────────────
   DOM RESTRUCTURE — runs synchronously so
   panes exist before roles.js fires
─────────────────────────────────────────── */
(function setupBranchPanes() {
  const container = document.getElementById("branchSelect");
  if (!container) return;

  const items = [...container.querySelectorAll(".branch-item")];
  items.forEach((el, i) => (el.dataset.index = i));

  container.innerHTML = `
  <div class="branch-col">
    <div class="branch-col-header">Branches</div>
    <div id="branchLeftPane" class="branch-pane"></div>
  </div>
  <div class="branch-col-divider"></div>
  <div class="branch-col">
    <div class="branch-col-header">Selected</div>
    <div id="branchRightPane" class="branch-pane"></div>
  </div>
`;

  const leftPane = document.getElementById("branchLeftPane");
  items.forEach((el) => leftPane.appendChild(el));
})();

/* ───────────────────────────────────────────
   DOM RESTRUCTURE — region equivalent.
   #regionSelect container of .region-item
   checkboxes, same shape as #branchSelect.
─────────────────────────────────────────── */
(function setupRegionPanes() {
  const container = document.getElementById("regionSelect");
  if (!container) return;

  const items = [...container.querySelectorAll(".region-item")];
  items.forEach((el, i) => (el.dataset.index = i));

  container.innerHTML = `
  <div class="branch-col">
    <div class="branch-col-header">Regions</div>
    <div id="regionLeftPane" class="branch-pane"></div>
  </div>
  <div class="branch-col-divider"></div>
  <div class="branch-col">
    <div class="branch-col-header">Selected</div>
    <div id="regionRightPane" class="branch-pane"></div>
  </div>
`;

  const leftPane = document.getElementById("regionLeftPane");
  items.forEach((el) => leftPane.appendChild(el));
})();

/* ───────────────────────────────────────────
   BRANCH HELPERS
─────────────────────────────────────────── */
function sortCreateBranches() {
  const leftPane = document.getElementById("branchLeftPane");
  const rightPane = document.getElementById("branchRightPane");
  if (!leftPane || !rightPane) return;

  const allItems = [
    ...leftPane.querySelectorAll(".branch-item"),
    ...rightPane.querySelectorAll(".branch-item"),
  ];

  allItems.forEach((el) => {
    const checked = el.querySelector("input[type='checkbox']").checked;
    (checked ? rightPane : leftPane).appendChild(el);
  });

  [...leftPane.querySelectorAll(".branch-item")]
    .sort(
      (a, b) =>
        (parseInt(a.dataset.index) || 0) - (parseInt(b.dataset.index) || 0),
    )
    .forEach((el) => leftPane.appendChild(el));
}

function updateCreateBranchCounter() {
  const count = document.querySelectorAll(
    "#branchLeftPane input[type='checkbox']:checked, #branchRightPane input[type='checkbox']:checked",
  ).length;

  const counter = document.getElementById("branchCounter");
  if (counter) counter.textContent = `Selected: ${count}`;
}

function createModalIsBranchManagerRole() {
  return (
    document.getElementById("createRoleSelect")?.value === "branch_manager"
  );
}

/* ───────────────────────────────────────────
   REGION HELPERS (mirror of branch helpers)
─────────────────────────────────────────── */
function sortCreateRegions() {
  const leftPane = document.getElementById("regionLeftPane");
  const rightPane = document.getElementById("regionRightPane");
  if (!leftPane || !rightPane) return;

  const allItems = [
    ...leftPane.querySelectorAll(".region-item"),
    ...rightPane.querySelectorAll(".region-item"),
  ];

  allItems.forEach((el) => {
    const checked = el.querySelector("input[type='checkbox']").checked;
    (checked ? rightPane : leftPane).appendChild(el);
  });

  [...leftPane.querySelectorAll(".region-item")]
    .sort(
      (a, b) =>
        (parseInt(a.dataset.index) || 0) - (parseInt(b.dataset.index) || 0),
    )
    .forEach((el) => leftPane.appendChild(el));
}

function updateCreateRegionCounter() {
  const count = document.querySelectorAll(
    "#regionLeftPane input[type='checkbox']:checked, #regionRightPane input[type='checkbox']:checked",
  ).length;

  const counter = document.getElementById("regionCounter");
  if (counter) counter.textContent = `Selected: ${count}`;
}

function createModalIsRegionalRole() {
  return (
    document.getElementById("createRoleSelect")?.value === "regional_manager"
  );
}

/* ───────────────────────────────────────────
   SECTION VISIBILITY — branch vs region
   regional_manager: hide Branches, show Region
   everything else: show Branches (roles.js still
   controls audit-role hiding of the branch section
   on top of this), hide Region
─────────────────────────────────────────── */
function toggleBranchRegionSections() {
  const role = document.getElementById("createRoleSelect")?.value;
  const branchWrapper = document.getElementById("branchSectionWrapper");
  const regionWrapper = document.getElementById("regionSectionWrapper");
  const isRegional = role === "regional_manager";

  if (regionWrapper) regionWrapper.style.display = isRegional ? "" : "none";
  if (branchWrapper) branchWrapper.style.display = isRegional ? "none" : "";

  // disable/clear whichever picker just got hidden so it can't submit stale values
  if (isRegional) {
    document.querySelectorAll("#branchSelect input[type='checkbox']").forEach((cb) => {
      cb.checked = false;
      cb.disabled = true;
    });
    document.querySelectorAll("#regionSelect input[type='checkbox']").forEach((cb) => {
      cb.disabled = false;
    });
  } else {
    document.querySelectorAll("#regionSelect input[type='checkbox']").forEach((cb) => {
      cb.checked = false;
      cb.disabled = true;
    });
  }

  sortCreateBranches();
  sortCreateRegions();
  updateCreateBranchCounter();
  updateCreateRegionCounter();
}

/* ───────────────────────────────────────────
   SEARCH — filters right pane (unselected)
─────────────────────────────────────────── */
$(document).on("keyup", "#branchSearch", function () {
  const value = $(this).val().toUpperCase();

  $("#branchLeftPane .branch-item, #branchRightPane .branch-item").each(
    function () {
      $(this).toggle($(this).text().toUpperCase().includes(value));
    },
  );
});

$(document).on("keyup", "#regionSearch", function () {
  const value = $(this).val().toUpperCase();

  $("#regionLeftPane .region-item, #regionRightPane .region-item").each(
    function () {
      $(this).toggle($(this).text().toUpperCase().includes(value));
    },
  );
});

/* ───────────────────────────────────────────
   CHECKBOX CHANGE
─────────────────────────────────────────── */
$(document).on(
  "change",
  "#branchLeftPane input[type='checkbox'], #branchRightPane input[type='checkbox']",
  function () {
    // BRANCH MANAGER = single branch only. Checking one unchecks the rest,
    // giving radio-button behavior without swapping out the picker markup.
    if (createModalIsBranchManagerRole() && this.checked) {
      document
        .querySelectorAll(
          "#branchLeftPane input[type='checkbox']:checked, #branchRightPane input[type='checkbox']:checked",
        )
        .forEach((cb) => {
          if (cb !== this) cb.checked = false;
        });
    }

    sortCreateBranches();
    updateCreateBranchCounter();
  },
);

$(document).on(
  "change",
  "#regionLeftPane input[type='checkbox'], #regionRightPane input[type='checkbox']",
  function () {
    // REGIONAL MANAGER = single region only, same radio behavior as branch_manager.
    if (createModalIsRegionalRole() && this.checked) {
      document
        .querySelectorAll(
          "#regionLeftPane input[type='checkbox']:checked, #regionRightPane input[type='checkbox']:checked",
        )
        .forEach((cb) => {
          if (cb !== this) cb.checked = false;
        });
    }

    sortCreateRegions();
    updateCreateRegionCounter();
  },
);

/* ───────────────────────────────────────────
   PRESET ROLE (e.g. "Add Branch Manager" / "Add Regional User" button)
   Opened via a trigger with data-preset-role="branch_manager" or
   "regional_manager" — locks the role, hides the dropdown, shows the
   readonly display with the matching label, and lets roles.js configure
   the branch/region picker for that role automatically.

   ROLE SCOPE (e.g. "Add User" vs "Add Audit User")
   Opened via a trigger with data-role-scope="hr" | "audit" —
   filters the role dropdown down to just that scope's options
   (options without a data-scope default to "hr"). Falls back to
   "hr" if the trigger has no data-role-scope at all, so any button
   that predates this feature keeps working unmodified.
─────────────────────────────────────────── */
const PRESET_ROLE_DISPLAY_LABELS = {
  branch_manager: "BRANCH",
  regional_manager: "REGIONAL",
};

document
  .getElementById("createUserModal")
  ?.addEventListener("show.bs.modal", function (e) {
    const trigger = e.relatedTarget;
    const presetRole = trigger?.dataset?.presetRole || "";
    const roleScope = trigger?.dataset?.roleScope || "hr";

    const roleSelect = document.getElementById("createRoleSelect");
    const selectGroup = document.getElementById("roleSelectGroup");
    const displayGroup = document.getElementById("roleDisplayGroup");
    const displayInput = document.getElementById("roleDisplayReadonly");

    roleSelect.querySelectorAll("option[value]").forEach((opt) => {
      if (!opt.value) return; // keep the "Select Role" placeholder untouched

      // branch_manager and regional_manager are never manually selectable
      // from the dropdown — they're only ever set programmatically via
      // presetRole. Leave their permanent `hidden` attribute alone
      // regardless of scope, otherwise switching to the HR scope would
      // un-hide them in "Add User".
      if (opt.value === "branch_manager" || opt.value === "regional_manager") return;

      const scope = opt.dataset.scope || "hr";
      const matches = scope === roleScope;
      opt.hidden = !matches;
      opt.disabled = !matches;
    });

    if (presetRole) {
      roleSelect.value = presetRole;
      roleSelect.required = false; // hidden fields can't satisfy native required validation
      selectGroup.style.display = "none";
      displayGroup.style.display = "";
      displayInput.value = PRESET_ROLE_DISPLAY_LABELS[presetRole] || presetRole.toUpperCase();
    } else {
      roleSelect.value = "";
      roleSelect.required = true;
      selectGroup.style.display = "";
      displayGroup.style.display = "none";
    }

    // re-run role-dependent UI (branch/region picker enable/disable, single-select label,
    // and — for audit roles — hiding the branch/region section entirely)
    if (typeof updateFieldsByRole === "function") updateFieldsByRole();
    toggleBranchRegionSections();
  });

// also re-toggle if the role dropdown itself changes (non-preset "Add User" flow,
// in case regional_manager is ever exposed there in the future)
$(document).on("change", "#createRoleSelect", function () {
  toggleBranchRegionSections();
});

/* ───────────────────────────────────────────
   FORM SUBMIT
─────────────────────────────────────────── */
document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("#createUserModal form");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const role = document.getElementById("createRoleSelect")?.value;
    if (role === "branch_manager") {
      const checkedCount = document.querySelectorAll(
        "#branchLeftPane input[type='checkbox']:checked, #branchRightPane input[type='checkbox']:checked",
      ).length;
      if (checkedCount !== 1) {
        Swal.fire({
          icon: "warning",
          title: "Branch Required",
          text: "Please select exactly one branch for a Branch Manager.",
        });
        return;
      }
    }

    if (role === "regional_manager") {
      const checkedCount = document.querySelectorAll(
        "#regionLeftPane input[type='checkbox']:checked, #regionRightPane input[type='checkbox']:checked",
      ).length;
      if (checkedCount !== 1) {
        Swal.fire({
          icon: "warning",
          title: "Region Required",
          text: "Please select exactly one region for a Regional Manager.",
        });
        return;
      }
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtn = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = `
      <span class="spinner-border spinner-border-sm me-1"></span>
      Creating...
    `;

    try {
      const formData = new FormData(form);

      const res = await fetch(form.action, {
        method: "POST",
        body: formData,
      });

      const data = await res.json();

      if (data.status === "success") {
        await Swal.fire({
          icon: "success",
          title: "Success",
          text: data.message || "User created successfully",
        });

        form.reset();

        const modalEl = document.getElementById("createUserModal");
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        location.reload();
      } else {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: data.message || "Failed to create user",
        });
      }
    } catch (err) {
      console.error(err);
      Swal.fire({
        icon: "error",
        title: "Server Error",
        text: "Something went wrong",
      });
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtn;
    }
  });

  updateCreateBranchCounter();
  updateCreateRegionCounter();
  toggleBranchRegionSections();
});