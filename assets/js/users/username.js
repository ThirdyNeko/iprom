const firstName = document.getElementById("first_name");
const lastName = document.getElementById("last_name");
const suffix = document.getElementById("suffix");
const username = document.getElementById("username");

// Same rule as the backend: "FIRST LAST SUFFIX", uppercase, suffix optional
function updateUsername() {
  const first = firstName.value.trim();
  const last = lastName.value.trim();
  const suf = suffix ? suffix.value.trim() : "";

  username.value = [first, last, suf].filter(Boolean).join(" ").toUpperCase();
}

// runs WHILE typing
firstName.addEventListener("input", updateUsername);
lastName.addEventListener("input", updateUsername);

// suffix is a <select>, so it fires "change"
if (suffix) suffix.addEventListener("change", updateUsername);
