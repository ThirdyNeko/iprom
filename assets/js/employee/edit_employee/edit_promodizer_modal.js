document.addEventListener("DOMContentLoaded", function () {
  // ============================================================
  // BACK BUTTON — return to promodizer list with filters restored
  // ============================================================
  const backBtn = document.getElementById("backBtn");
  if (backBtn) {
    backBtn.addEventListener("click", function () {
      window.location.href = "promodizers.php?restore=1";
    });
  }

  // ============================================================
  // EMPLOYEE PICTURE — fetch and display (read-only)
  // check_employee_picture.php keys off the actual employee_id
  // (e.g. "EMP-..."), not the promodizer record id in the URL —
  // so this is exposed as a function and called from
  // edit_promodizer.js once employee.employee_id is known
  // (see loadEmployeePage()).
  // ============================================================
  window.loadEmployeePicture = function (employeeId) {
    const box = document.getElementById("editEmployeePictureBox");
    if (!box) return;

    if (!employeeId) {
      box.innerHTML = '<div class="no-picture-label">No Photo</div>';
      return;
    }

    fetch(
      "functions/check_employee_picture.php?employee_id=" +
        encodeURIComponent(employeeId),
    )
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (data.exists && data.picture_data) {
          box.innerHTML = "";
          const img = document.createElement("img");
          img.src = data.picture_data;
          img.alt = "Employee Photo";
          box.appendChild(img);
        } else {
          box.innerHTML = '<div class="no-picture-label">No Photo</div>';
        }
      })
      .catch(function (err) {
        console.error("Failed to load employee picture:", err);
        box.innerHTML = '<div class="no-picture-label">No Photo</div>';
      });
  };

  // ============================================================
  // REASON / HEADER TOGGLE LOGIC
  // Also toggles visibility of the date-separated-group vs
  // date-start-group fields based on the selected reason.
  // ============================================================
  const reasonSelect = document.getElementById("editReasonUpdate");
  const employmentStatusSelect = document.getElementById(
    "editEmploymentStatus",
  );

  const thDateSeparated = document.getElementById("thDateSeparated");
  const thDateReturned = document.getElementById("thDateReturned");
  const thStartDate = document.getElementById("thStartDate");
  const thEndDate = document.getElementById("thEndDate");

  const dateSeparatedGroup = document.querySelectorAll(".date-separated-group");
  const dateStartGroup = document.querySelectorAll(".date-start-group");

  function updateHeaders() {
    const value = reasonSelect.value;

    thDateSeparated.textContent = "Date Separated";
    thDateReturned.textContent = "Date Returned";
    thStartDate.textContent = "Start";
    thEndDate.textContent = "End";

    const effectivityReasons = [
      "RESIGNED",
      "PULL-OUT / END OF CONTRACT",
      "BLACKLISTED / AWOL / TERMINATED",
      "DECEASED",
      "TRANSFER BRANCH",
      "REMOVE BRANCH/BRAND",
    ];

    const leaveReasons = ["EMERGENCY LEAVE", "MATERNITY LEAVE"];

    if (effectivityReasons.includes(value)) {
      thDateSeparated.textContent = "Effectivity Date";
      thStartDate.textContent = "Effectivity Date";
    }

    if (leaveReasons.includes(value)) {
      thDateSeparated.textContent = "Start";
      thDateReturned.textContent = "End";
    }

    if (employmentStatusSelect.value === "PERMANENT") {
      thStartDate.textContent = "Effectivity Date";
    }

    // Toggle which date group is visible.
    // NOTE: adjust this condition to match your actual business
    // rule for which reasons use "separated/return" vs "start/end".
    if (effectivityReasons.includes(value) || leaveReasons.includes(value)) {
      dateSeparatedGroup.forEach((el) => el.classList.remove("d-none"));
      dateStartGroup.forEach((el) => el.classList.add("d-none"));
    } else {
      dateSeparatedGroup.forEach((el) => el.classList.add("d-none"));
      dateStartGroup.forEach((el) => el.classList.remove("d-none"));
    }
  }

  reasonSelect.addEventListener("change", updateHeaders);
  employmentStatusSelect.addEventListener("change", updateHeaders);
  updateHeaders();

  // ============================================================
  // ADDRESS FIELD HOVER-TOOLTIP LOGIC
  // Shows full value on hover for Province, Municipality,
  // Barangay, and Street — useful when text is too long
  // to display fully inside the input.
  // ============================================================
  const addressFieldIds = [
    "editProvince",
    "editMunicipality",
    "editBarangay",
    "editStreet",
  ];

  function setAddressTooltip(el) {
    if (!el) return;
    if (el.tagName === "SELECT") {
      const selectedOption = el.options[el.selectedIndex];
      el.title = selectedOption ? selectedOption.text : "";
    } else {
      el.title = el.value || "";
    }
  }

  function refreshAddressTooltips() {
    addressFieldIds.forEach(function (id) {
      setAddressTooltip(document.getElementById(id));
    });
  }

  // Live update as values change
  addressFieldIds.forEach(function (id) {
    const el = document.getElementById(id);
    if (!el) return;

    if (el.tagName === "SELECT") {
      el.addEventListener("change", function () {
        setAddressTooltip(el);
      });
    } else {
      el.addEventListener("input", function () {
        setAddressTooltip(el);
      });
    }
  });

  // Run once on load in case fields are pre-filled server-side
  refreshAddressTooltips();

  // Poll every 500ms to catch values set programmatically
  // (e.g. jQuery .val() from an AJAX response), since those
  // don't fire native "input"/"change" events.
  setInterval(refreshAddressTooltips, 500);

  // Expose globally so edit_promodizer.js can call this after
  // populating the form fields via AJAX
  window.refreshAddressTooltips = refreshAddressTooltips;
});
