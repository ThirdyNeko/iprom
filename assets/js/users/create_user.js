// assets/js/users/create_user.js

/* ───────────────────────────────────────────
   DOM RESTRUCTURE — runs synchronously so
   panes exist before roles.js fires
─────────────────────────────────────────── */
(function setupBranchPanes() {
  const container = document.getElementById("branchSelect");
  if (!container) return;

  // stamp indices before moving elements
  const items = [...container.querySelectorAll(".branch-item")];
  items.forEach((el, i) => (el.dataset.index = i));

  // build two-pane layout
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

  // all items start unchecked → right pane
  const leftPane = document.getElementById("branchLeftPane");
  items.forEach((el) => leftPane.appendChild(el));
})();

/* ───────────────────────────────────────────
   BRANCH HELPERS
─────────────────────────────────────────── */
function sortCreateBranches() {
  const leftPane = document.getElementById("branchLeftPane"); // Branches
  const rightPane = document.getElementById("branchRightPane"); // Selected
  if (!leftPane || !rightPane) return;

  const allItems = [
    ...leftPane.querySelectorAll(".branch-item"),
    ...rightPane.querySelectorAll(".branch-item"),
  ];

  // distribute between panes
  allItems.forEach((el) => {
    const checked = el.querySelector("input[type='checkbox']").checked;
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
   SEARCH — filters right pane (unselected)
─────────────────────────────────────────── */
$(document).on("keyup", "#branchSearch", function () {
  const value = $(this).val().toUpperCase();

  // search both panes
  $("#branchLeftPane .branch-item, #branchRightPane .branch-item").each(
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

/* ───────────────────────────────────────────
   PRESET ROLE (e.g. "Add Branch Manager" button)
   Opened via a trigger with data-preset-role="branch_manager" —
   locks the role, hides the dropdown, and lets roles.js configure
   the branch picker for that role automatically.
─────────────────────────────────────────── */
document
  .getElementById("createUserModal")
  ?.addEventListener("show.bs.modal", function (e) {
    const trigger = e.relatedTarget;
    const presetRole = trigger?.dataset?.presetRole || "";

    const roleSelect = document.getElementById("createRoleSelect");
    const selectGroup = document.getElementById("roleSelectGroup");
    const displayGroup = document.getElementById("roleDisplayGroup");

    if (presetRole) {
      roleSelect.value = presetRole;
      roleSelect.required = false; // hidden fields can't satisfy native required validation
      selectGroup.style.display = "none";
      displayGroup.style.display = "";
    } else {
      roleSelect.value = "";
      roleSelect.required = true;
      selectGroup.style.display = "";
      displayGroup.style.display = "none";
    }

    // re-run role-dependent UI (branch picker enable/disable, single-select label)
    if (typeof updateFieldsByRole === "function") updateFieldsByRole();
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
});
