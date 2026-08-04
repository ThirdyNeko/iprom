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

// 🔒 Live password strength feedback
function checkPasswordStrength(pw) {
  const checks = {
    length: pw.length >= 12,
    upper: /[A-Z]/.test(pw),
    lower: /[a-z]/.test(pw),
    number: /[0-9]/.test(pw),
    special: /[^A-Za-z0-9]/.test(pw),
  };
  return Object.entries(checks)
    .filter(([, ok]) => !ok)
    .map(([key]) => key);
}

const newPasswordInput = document.getElementById("new_password");
const strengthFeedback = document.getElementById("passwordStrengthFeedback");

if (newPasswordInput && strengthFeedback) {
  newPasswordInput.addEventListener("input", function () {
    const failed = checkPasswordStrength(this.value);
    const labels = {
      length: "12+ characters",
      upper: "uppercase letter",
      lower: "lowercase letter",
      number: "number",
      special: "special character",
    };
    strengthFeedback.textContent = failed.length
      ? "Missing: " + failed.map((f) => labels[f]).join(", ")
      : "Strong password ✓";
    strengthFeedback.style.color = failed.length ? "#dc2626" : "#16a34a";
  });
}

// AJAX form submission with SweetAlert
document
  .getElementById("changePasswordForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();

    // 🔒 Block submit client-side if strength requirements aren't met
    const newPwValue = this.querySelector('[name="new_password"]').value;
    if (checkPasswordStrength(newPwValue).length > 0) {
      Swal.fire({
        icon: "warning",
        title: "Weak Password",
        text: "Please meet all password requirements before submitting.",
      });
      return;
    }

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
            location.reload();
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
