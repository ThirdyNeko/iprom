// assets/js/users/create_user.js

/* ───────────────────────────────────────────
   INDEX INIT — runs synchronously before
   roles.js calls sortCreateBranches()
─────────────────────────────────────────── */
document.querySelectorAll("#branchSelect .branch-item").forEach((el, i) => {
  el.dataset.index = i;
});

/* ───────────────────────────────────────────
   BRANCH HELPERS  (prefixed to avoid
   collision with users_modal.js globals)
─────────────────────────────────────────── */
function sortCreateBranches() {
  const container = document.getElementById("branchSelect");
  if (!container) return;

  const items = [...container.querySelectorAll(".branch-item")];
  if (!items.length) return;

  items.sort((a, b) => {
    const aChecked = a.querySelector("input[type='checkbox']").checked ? 1 : 0;
    const bChecked = b.querySelector("input[type='checkbox']").checked ? 1 : 0;

    if (aChecked !== bChecked) return bChecked - aChecked;

    return (parseInt(a.dataset.index) || 0) - (parseInt(b.dataset.index) || 0);
  });

  items.forEach((el) => container.appendChild(el));

  container.scrollTop = 0;
}

function updateCreateBranchCounter() {
  const count = document.querySelectorAll(
    "#branchSelect input[type='checkbox']:checked",
  ).length;

  const counter = document.getElementById("branchCounter");
  if (counter) counter.textContent = `Selected: ${count}`;
}

/* ───────────────────────────────────────────
   SEARCH
─────────────────────────────────────────── */
$(document).on("keyup", "#branchSearch", function () {
  const value = $(this).val().toUpperCase();

  $("#branchSelect .branch-item").each(function () {
    $(this).toggle($(this).text().toUpperCase().includes(value));
  });
});

/* ───────────────────────────────────────────
   CHECKBOX CHANGE
─────────────────────────────────────────── */
$(document).on("change", "#branchSelect input[type='checkbox']", function () {
  sortCreateBranches();
  updateCreateBranchCounter();
});

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
