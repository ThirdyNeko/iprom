// assets/js/users/create_user.js

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

        // Reset form
        form.reset();

        // Close modal
        const modalEl = document.getElementById("createUserModal");

        const modal = bootstrap.Modal.getInstance(modalEl);

        if (modal) {
          modal.hide();
        }

        // Reload page
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
});

function sortBranches() {
  const container = $("#branchSelect");

  const items = container.find(".branch-item").toArray();

  // STEP 1: capture current positions
  const positions = new Map();

  items.forEach((el) => {
    positions.set(el, el.getBoundingClientRect().top);
  });

  // STEP 2: sort logic (same as before)
  items.sort((a, b) => {
    const aChecked = $(a).find("input[type='checkbox']").prop("checked")
      ? 1
      : 0;
    const bChecked = $(b).find("input[type='checkbox']").prop("checked")
      ? 1
      : 0;

    if (aChecked !== bChecked) {
      return bChecked - aChecked;
    }

    return $(a).data("index") - $(b).data("index");
  });

  // STEP 3: re-append (DOM update)
  items.forEach((el) => container[0].appendChild(el));

  // STEP 4: animate movement
  items.forEach((el) => {
    const oldTop = positions.get(el);
    const newTop = el.getBoundingClientRect().top;

    const diff = oldTop - newTop;

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

$(document).ready(function () {
  updateBranchCounter();
});

function updateBranchCounter() {
  const count = $("#branchSelect input[type='checkbox']:checked").length;
  $("#branchCounter").text(`Selected: ${count}`);
}

$("#branchSearch").on("keyup", function () {
  const value = $(this).val().toUpperCase();

  $("#branchSelect .branch-item").each(function () {
    const text = $(this).text().toUpperCase();

    $(this).toggle(text.includes(value));
  });
});

$(document).ready(function () {
  $("#branchSelect .branch-item").each(function (index) {
    $(this).attr("data-index", index);
  });
});

$(document).on("change", "#branchSelect input[type='checkbox']", function () {
  sortBranches();
  updateBranchCounter();
});
