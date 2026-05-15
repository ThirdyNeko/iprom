document.querySelectorAll(".toggle-password").forEach((span) => {
  span.addEventListener("click", () => {
    const target = document.getElementById(span.dataset.target);
    const icon = span.querySelector("i");
    if (target.type === "password") {
      target.type = "text";
      icon.classList.replace("bi-eye-slash", "bi-eye");
    } else {
      target.type = "password";
      icon.classList.replace("bi-eye", "bi-eye-slash");
    }
  });
});

// AJAX form submission with SweetAlert
document
  .getElementById("changePasswordForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    fetch("functions/change_password.php", { method: "POST", body: formData })
      .then((res) => res.json())
      .then((data) => {
        Swal.fire({
          icon: data.status === "success" ? "success" : "error",
          title: data.status === "success" ? "Password Changed!" : "Oops...",
          text: data.message,
          confirmButtonText: "OK",
        }).then(() => {
          if (data.status === "success") {
            location.reload(); // 🔥 refresh after success
          }
        });

        if (data.status === "success") this.reset();
      })
      .catch((err) => {
        console.error(err);
        Swal.fire({
          icon: "error",
          title: "Error!",
          text: "Something went wrong. Please try again.",
          confirmButtonText: "OK",
        });
      })
      .finally(() => (submitBtn.disabled = false));
  });
