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
   COUNTER (FIXED)
─────────────────────────────────────────── */
function updateBranchCounter() {
  const $modal = $("#userViewModal");

  const count = $modal.find("#v_branch .branch-checkbox:checked").length;

  console.log("Counter updating:", count);

  $modal.find("#branchCounter").text(`Selected: ${count}`);
}

/* ───────────────────────────────────────────
   SEARCH (FIXED)
─────────────────────────────────────────── */
$(document).on("input", "#branchSearch", function () {
  const search = $(this).val().trim().toUpperCase();

  $("#userViewModal #v_branch .branch-item").each(function () {
    const text = $(this).find("label").text().trim().toUpperCase();

    $(this).toggle(search === "" || text.includes(search));
  });
});

/* ───────────────────────────────────────────
   CHECKBOX CHANGE
─────────────────────────────────────────── */
$(document).on("change", "#v_branch input[type='checkbox']", function () {
  sortBranches();
  updateBranchCounter();
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

      const assigned = data.branch
        ? data.branch.split(",").map((c) => c.trim())
        : [];

      const normalizedAssigned = assigned.map((v) => v.trim());
      const allBranches = data.branch_names ?? {};

      /* ───── populate fields ───── */
      $("#v_username").val(username);
      $("#v_first_name").val(data.first_name);
      $("#v_last_name").val(data.last_name);
      $("#v_position").val(data.position);
      const roleLabels = {
        admin: "ADMIN",
        super_admin: "SUPER ADMIN",
        staff: "STAFF",
        supervisor: "SUPERVISOR",
      };

      $("#v_role").val(roleLabels[data.role] ?? data.role);
      $("#v_created_at").val(formatMDY(data.created_at));
      $("#v_updated_at").val(formatMDY(data.updated_at));

      /* ───── search toggle ───── */
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

      $("#userViewModal").modal("show");
    },
  });
});

/* ───────────────────────────────────────────
   SAVE BRANCHES
─────────────────────────────────────────── */
$(document).on("click", "#saveBranchBtn", function () {
  const username = $("#v_username").val();

  const selected = $("#v_branch .branch-checkbox:checked")
    .map((_, el) => el.value)
    .get()
    .join(",");

  Swal.fire({
    icon: "question",
    title: "Update Branches?",
    text: `Save branch changes for "${username}"?`,
    showCancelButton: true,
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.ajax({
      url: "functions/update_user_branches.php",
      type: "POST",
      data: { username, branches: selected },
      dataType: "json",
      success: function (res) {
        if (res.success) {
          Swal.fire("Saved!", "Branches updated.", "success").then(() =>
            location.reload(),
          );
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
