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
    sortCreateBranches();
    updateCreateBranchCounter();
  },
);

/* ───────────────────────────────────────────
   FORM SUBMIT
─────────────────────────────────────────── */
document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("#createUserModal form");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

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
