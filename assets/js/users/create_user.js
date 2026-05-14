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
