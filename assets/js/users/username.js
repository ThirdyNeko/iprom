const firstName = document.getElementById("first_name");
const lastName = document.getElementById("last_name");
const username = document.getElementById("username");

function updateUsername() {
  const first = firstName.value.trim();
  const last = lastName.value.trim();

  username.value = [first, last].filter(Boolean).join(" ").toLowerCase();
}

// runs WHILE typing
firstName.addEventListener("input", updateUsername);
lastName.addEventListener("input", updateUsername);
