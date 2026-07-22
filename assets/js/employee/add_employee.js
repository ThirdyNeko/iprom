document.addEventListener("DOMContentLoaded", async function () {
  const form = document.getElementById("addEmployeeForm");
  const btn = form.querySelector('button[type="submit"]');
  const employmentStatus = document.getElementById("employmentStatus");
  const dateRangeFields = document.getElementById("dateRangeFields");
  const rovingField = document.getElementById("rovingField");
  const rovingContainer = document.getElementById("rovingContainer");
  const remarks = form.querySelector('textarea[name="remarks"]');
  const remarksCount = document.getElementById("remarksCount");
  const subStatus = document.getElementById("subStatus");
  const genderInput = form.querySelector('select[name="gender"]');
  const birthdayInput = form.querySelector('input[name="birthday"]');
  const noMiddleName = document.getElementById("noMiddleName");
  const middleNameInput = document.getElementById("middleName");

  // NEW fields
  const maritalStatusInput = document.getElementById("maritalStatus");
  const contactNumberInput = document.getElementById("contactNumber");
  const biometricNumberInput = document.getElementById("biometricNumber");

  // Categories dropdown
  const catAll = document.getElementById("catAll");
  const catItems = document.querySelectorAll(".category-item");
  const categoriesInput = document.getElementById("categoriesInput");
  const categoriesBtn = document.getElementById("categoriesDropdownBtn");

  // Address fields (now cascading selects instead of free text)
  const provinceInput = document.getElementById("province");
  const municipalityInput = document.getElementById("municipality");
  const barangayInput = document.getElementById("barangay");
  const streetInput = document.getElementById("street");

  // Hidden fields that carry the human-readable names alongside the codes
  const provinceNameInput = document.getElementById("provinceName");
  const municipalityNameInput = document.getElementById("municipalityName");
  const barangayNameInput = document.getElementById("barangayName");

  const mainBranchSelect = form.querySelector('select[name="branch"]');
  const mainBrandSelect = form.querySelector('select[name="brand"]');
  const agencySelect = form.querySelector('select[name="agency"]');

  const multiBrandField = document.getElementById("multiBrandField");
  const multiBrandContainer = document.getElementById("multiBrandContainer");

  noMiddleName.addEventListener("change", function () {
    if (this.checked) {
      middleNameInput.value = "";
      middleNameInput.disabled = true;
    } else {
      middleNameInput.disabled = false;
    }
  });

  // Contact number: digits only, max 11
  contactNumberInput.addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, "").slice(0, 11);
  });

  // Biometric number: digits only, max 7 (optional field)
  biometricNumberInput.addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, "").slice(0, 7);
  });

  // =========================
  // Categories dropdown (default "All", collapsible individual picks)
  // =========================
  function updateCategoriesInputAndLabel() {
    if (catAll.checked) {
      categoriesInput.value = "ALL";
      categoriesBtn.textContent = "All";
      return;
    }

    const checked = Array.from(catItems)
      .filter((cb) => cb.checked)
      .map((cb) => cb.value);

    categoriesInput.value = checked.join(",");
    categoriesBtn.textContent = checked.length
      ? checked.join(", ")
      : "None selected";
  }

  catAll.addEventListener("change", function () {
    catItems.forEach((cb) => {
      cb.disabled = catAll.checked;
      if (catAll.checked) cb.checked = true;
    });
    updateCategoriesInputAndLabel();
  });

  catItems.forEach((cb) => {
    cb.addEventListener("change", function () {
      const allChecked = Array.from(catItems).every((item) => item.checked);
      const noneChecked = Array.from(catItems).every((item) => !item.checked);

      if (allChecked) {
        catAll.checked = true;
        catItems.forEach((item) => (item.disabled = true));
      }
      if (noneChecked) {
        catAll.checked = false;
      }
      updateCategoriesInputAndLabel();
    });
  });

  updateCategoriesInputAndLabel();

  // =========================
  // Fetch branch-brand availability mapping
  // =========================
  let branchBrandPairs = [];
  try {
    const res = await fetch("functions/get_available_branches_brands.php");
    branchBrandPairs = await res.json(); // [{branch_name, brand_name, required_count, assigned_count}]
  } catch (err) {
    console.error("Failed to fetch branch-brand data", err);
  }

  // =========================
  // Convert all inputs to uppercase
  // =========================
  form.querySelectorAll('input[type="text"]').forEach((input) => {
    input.addEventListener(
      "input",
      () => (input.value = input.value.toUpperCase()),
    );
  });
  form.querySelectorAll("select").forEach((select) => {
    select.addEventListener("change", () => {
      if (select.value) select.value = select.value.toUpperCase();
    });
  });

  // =========================
  // PH Address cascade: Province -> Municipality -> Barangay
  // =========================
  async function loadProvinces() {
    try {
      const res = await fetch("functions/get_provinces.php");
      const provinces = await res.json();

      provinceInput.innerHTML =
        '<option value="" disabled selected>-- Select Province --</option>';

      provinces.forEach((p) => {
        provinceInput.appendChild(new Option(p.name, p.code));
      });
    } catch (err) {
      console.error("Failed to load provinces", err);
      Swal.fire(
        "Error",
        "Failed to load provinces. Please refresh and try again.",
        "error",
      );
    }
  }

  async function loadMunicipalities(provinceCode) {
    municipalityInput.innerHTML =
      '<option value="" disabled selected>-- Select Municipality --</option>';
    barangayInput.innerHTML =
      '<option value="" disabled selected>-- Select Barangay --</option>';
    municipalityInput.disabled = true;
    barangayInput.disabled = true;

    if (!provinceCode) return;

    try {
      const res = await fetch(
        `functions/get_municipalities.php?province_code=${encodeURIComponent(provinceCode)}`,
      );
      const municipalities = await res.json();

      municipalities.forEach((m) => {
        municipalityInput.appendChild(new Option(m.name, m.code));
      });

      municipalityInput.disabled = false;
    } catch (err) {
      console.error("Failed to load municipalities", err);
      Swal.fire("Error", "Failed to load municipalities.", "error");
    }
  }

  async function loadBarangays(municipalityCode) {
    barangayInput.innerHTML =
      '<option value="" disabled selected>-- Select Barangay --</option>';
    barangayInput.disabled = true;

    if (!municipalityCode) return;

    try {
      const res = await fetch(
        `functions/get_barangays.php?municipality_code=${encodeURIComponent(municipalityCode)}`,
      );
      const barangays = await res.json();

      barangays.forEach((b) => {
        barangayInput.appendChild(new Option(b.name, b.code));
      });

      barangayInput.disabled = false;
    } catch (err) {
      console.error("Failed to load barangays", err);
      Swal.fire("Error", "Failed to load barangays.", "error");
    }
  }

  provinceInput.addEventListener("change", () => {
    provinceNameInput.value =
      provinceInput.options[provinceInput.selectedIndex]?.text || "";
    municipalityNameInput.value = "";
    barangayNameInput.value = "";
    loadMunicipalities(provinceInput.value);
  });

  municipalityInput.addEventListener("change", () => {
    municipalityNameInput.value =
      municipalityInput.options[municipalityInput.selectedIndex]?.text || "";
    barangayNameInput.value = "";
    loadBarangays(municipalityInput.value);
  });

  barangayInput.addEventListener("change", () => {
    barangayNameInput.value =
      barangayInput.options[barangayInput.selectedIndex]?.text || "";
  });

  // =========================
  // Toggle fields based on Employment Status
  // =========================
  employmentStatus.addEventListener("change", toggleFields);
  subStatus.addEventListener("change", toggleFields);

  function toggleFields() {
    const empStatus = employmentStatus.value;
    const sub = subStatus.value;
    multiBrandField.classList.add("d-none");
    multiBrandField
      .querySelectorAll(".multi-brand-select")
      .forEach((s) => (s.required = false));

    // Reset roving
    rovingField.classList.add("d-none");
    rovingField
      .querySelectorAll(".roving-select")
      .forEach((s) => (s.required = false));

    // Date range: start_date now applies to ALL employment statuses (incl. PERMANENT).
    // end_date is still only relevant for SEASONAL / RELIEVER.
    const startDateField = dateRangeFields.querySelector(
      'input[name="start_date"]',
    );
    const endDateField = dateRangeFields.querySelector(
      'input[name="end_date"]',
    );

    startDateField.required = true;
    startDateField.disabled = false;

    const needsEndDate = empStatus === "SEASONAL" || empStatus === "RELIEVER";
    endDateField.required = needsEndDate;
    endDateField.disabled = !needsEndDate;
    if (!needsEndDate) endDateField.value = "";

    // Show roving when MULTI BRANCH
    if (sub === "MULTI BRANCH" || sub === "HYBRID") {
      rovingField.classList.remove("d-none");
      rovingField
        .querySelectorAll(".roving-select")
        .forEach((s) => (s.required = true));
    }
    if (sub === "MULTI BRAND" || sub === "HYBRID") {
      multiBrandField.classList.remove("d-none");
      multiBrandField
        .querySelectorAll(".multi-brand-select")
        .forEach((s) => (s.required = true));
    }
  }

  // =========================
  // Add / Remove roving rows
  // =========================
  rovingContainer.addEventListener("click", function (e) {
    const row = e.target.closest(".roving-row");
    if (!row) return;

    if (e.target.classList.contains("add-branch")) {
      const clone = row.cloneNode(true);
      const select = clone.querySelector("select");

      select.value = "";
      rovingContainer.appendChild(clone);

      requestAnimationFrame(() => {
        document.querySelectorAll(".roving-select").forEach((sel) => {
          populateRovingSelect(sel);
        });
      });
    }

    if (e.target.classList.contains("remove-branch")) {
      const rows = rovingContainer.querySelectorAll(".roving-row");
      if (rows.length > 1) row.remove();
      else row.querySelector("select").value = "";
    }
  });

  multiBrandContainer.addEventListener("click", function (e) {
    const row = e.target.closest(".multi-brand-row");
    if (!row) return;

    if (e.target.classList.contains("add-brand")) {
      const clone = row.cloneNode(true);
      const select = clone.querySelector("select");

      select.value = "";
      multiBrandContainer.appendChild(clone);

      // wait until DOM is fully attached
      requestAnimationFrame(() => {
        populateMultiBrandSelect(
          select,
          mainBranchSelect.value,
          mainBrandSelect.value,
        );
      });
    }

    if (e.target.classList.contains("remove-brand")) {
      const rows = multiBrandContainer.querySelectorAll(".multi-brand-row");
      if (rows.length > 1) row.remove();
      else row.querySelector("select").value = "";
    }
  });

  // =========================
  // Remarks character count
  // =========================
  remarks.addEventListener("input", function () {
    remarksCount.textContent = `${this.value.length} / 100`;
  });

  // =========================
  // Populate main branch select with availability
  // =========================
  function populateBranchSelect() {
    const uniqueBranches = [
      ...new Set(branchBrandPairs.map((p) => p.branch_code)),
    ];

    mainBranchSelect.innerHTML =
      '<option value="" disabled selected>-- Select Branch --</option>';

    uniqueBranches.forEach((code) => {
      // get readable name
      const displayName =
        branchBrandPairs.find((p) => p.branch_code === code)?.branch_name ||
        code;

      const opt = new Option(displayName, code); // label = name, value = code

      const allFull = branchBrandPairs
        .filter((p) => p.branch_code === code)
        .every((p) => p.assigned_count >= p.required_count);

      if (allFull) {
        opt.disabled = true;
        opt.text += " (Full)";
      }

      mainBranchSelect.appendChild(opt);
    });
  }

  // =========================
  // Populate brand select based on branch
  // =========================
  function updateBrandSelect(selectBranch, selectBrand) {
    const branch = selectBranch.value;
    selectBrand.innerHTML =
      '<option value="" disabled selected>-- Select Brand --</option>';
    branchBrandPairs
      .filter((p) => p.branch_code === branch)
      .forEach((p) => {
        const opt = new Option(p.brand_name, p.brand_name);
        if (p.assigned_count >= p.required_count) {
          opt.disabled = true;
          opt.text += " (Full)";
        }
        selectBrand.appendChild(opt);
      });
  }

  // =========================
  // Populate roving selects
  // =========================
  function populateRovingSelect(select) {
    const currentBranch = mainBranchSelect.value;

    const selectedBranches = Array.from(
      rovingContainer.querySelectorAll(".roving-select"),
    )
      .filter((s) => s !== select)
      .map((s) => s.value)
      .filter(Boolean);

    const currentValue = select.value;

    const uniqueBranches = [
      ...new Set(branchBrandPairs.map((p) => p.branch_code)),
    ];

    select.innerHTML =
      '<option value="" disabled selected>-- Select Branch --</option>';

    uniqueBranches.forEach((code) => {
      const displayName =
        branchBrandPairs.find((p) => p.branch_code === code)?.branch_name ||
        code;

      // exclude main branch (by code)
      if (code === currentBranch) return;

      // exclude already selected
      if (selectedBranches.includes(code)) return;

      const opt = new Option(displayName, code); // label=name, value=code

      const allFull = branchBrandPairs
        .filter((p) => p.branch_code === code)
        .every((p) => p.assigned_count >= p.required_count);

      if (allFull) {
        opt.disabled = true;
        opt.text += " (Full)";
      }

      select.appendChild(opt);
    });

    // restore previous value if still valid
    if (
      currentValue &&
      !selectedBranches.includes(currentValue) &&
      currentValue !== currentBranch
    ) {
      select.value = currentValue;
    }
  }

  function populateMultiBrandSelect(select, selectedBranch, excludedBrand) {
    const branch = selectedBranch || mainBranchSelect.value;
    const brandToExclude = excludedBrand || mainBrandSelect.value;

    select.innerHTML =
      '<option value="" disabled selected>-- Select Brand --</option>';

    if (!branch) return;

    const filtered = branchBrandPairs.filter((p) => p.branch_code === branch);

    filtered.forEach((p) => {
      // use passed value, not global only
      if (p.brand_name === brandToExclude) return;

      const opt = new Option(p.brand_name, p.brand_name);

      if (p.assigned_count >= p.required_count) {
        opt.disabled = true;
        opt.text += " (Full)";
      }

      select.appendChild(opt);
    });
  }

  mainBranchSelect.addEventListener("change", () => {
    updateBrandSelect(mainBranchSelect, mainBrandSelect);

    const branch = mainBranchSelect.value;
    const brand = mainBrandSelect.value;

    requestAnimationFrame(() => {
      // refresh multi-brand
      document.querySelectorAll(".multi-brand-select").forEach((sel) => {
        populateMultiBrandSelect(sel, branch, brand);
      });

      // refresh ALL roving selects immediately
      document.querySelectorAll(".roving-select").forEach((sel) => {
        populateRovingSelect(sel);
      });
    });
  });

  mainBrandSelect.addEventListener("change", () => {
    const branch = mainBranchSelect.value;
    const brand = mainBrandSelect.value;

    requestAnimationFrame(() => {
      document.querySelectorAll(".multi-brand-select").forEach((sel) => {
        populateMultiBrandSelect(sel, branch, brand);
      });
    });
  });

  // Initial populate
  populateBranchSelect();
  updateBrandSelect(mainBranchSelect, mainBrandSelect);
  toggleFields();
  rovingContainer
    .querySelectorAll(".roving-select")
    .forEach((s) => populateRovingSelect(s));
  multiBrandContainer.querySelectorAll(".multi-brand-select").forEach((sel) => {
    populateMultiBrandSelect(
      sel,
      mainBranchSelect.value,
      mainBrandSelect.value,
    );
  });
  loadProvinces();

  rovingContainer.addEventListener("change", function (e) {
    if (e.target.classList.contains("roving-select")) {
      document.querySelectorAll(".roving-select").forEach((sel) => {
        populateRovingSelect(sel);
      });
    }
  });

  // =========================
  // Form submission
  // =========================

  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    // 🔒 Hard stop: block re-entry immediately, before any async work
    if (form.dataset.submitting === "1") return;
    form.dataset.submitting = "1";
    btn.disabled = true;
    const originalBtnText = btn.textContent;
    btn.textContent = "Saving...";

    // 🔒 Prevent modal close while submitting
    const modalEl = document.getElementById("addEmployeeModal");
    const dismissEls = modalEl.querySelectorAll(
      '[data-bs-dismiss="modal"], .btn-close',
    );
    dismissEls.forEach((b) => (b.disabled = true));
    const blockHide = (ev) => ev.preventDefault();
    modalEl.addEventListener("hide.bs.modal", blockHide);

    // ⏳ Reassure the user on a slow connection instead of silence
    const slowConnTimer = setTimeout(() => {
      btn.textContent = "Still saving... please wait";
    }, 4000);

    // 🔓 Single cleanup point — every exit path (validation return,
    // duplicate-check block, success, or error) funnels through here.
    try {
      const formData = new FormData(form);

      // Handle optional middle name & suffix (send NULL instead of empty)
      if (!formData.get("middle_name")) formData.delete("middle_name");
      if (!formData.get("suffix")) formData.delete("suffix");

      // Start / End date validation
      const startDateInput = form.querySelector('input[name="start_date"]');
      const endDateInput = form.querySelector('input[name="end_date"]');
      // remove empty date fields so PHP gets NULL
      if (!startDateInput.value) formData.delete("start_date");
      if (!endDateInput.value) formData.delete("end_date");
      const branch = mainBranchSelect.value;
      const brand = mainBrandSelect.value;
      const statusType = employmentStatus.value;
      const sub = subStatus.value;
      const dateHiredInput = form.querySelector('input[name="date_hired"]');
      const gender = genderInput.value;
      const birthday = birthdayInput.value;
      const agency = agencySelect.value;

      // NEW field values
      const maritalStatus = maritalStatusInput.value;
      const contactNumber = contactNumberInput.value;
      const biometricNumber = biometricNumberInput.value;
      const categories = categoriesInput.value; // "ALL" or comma list e.g. "TV,DA"

      // Address values: codes come from the selects, names from the hidden inputs
      const provinceCode = provinceInput.value;
      const provinceName = provinceNameInput.value.trim();
      const municipalityCode = municipalityInput.value;
      const municipalityName = municipalityNameInput.value.trim();
      const barangayCode = barangayInput.value;
      const barangayName = barangayNameInput.value.trim();
      const street = streetInput.value.trim();

      // Gender validation
      if (!gender) {
        return Swal.fire("Missing Gender", "Please select gender.", "warning");
      }

      // Marital Status validation
      if (!maritalStatus) {
        return Swal.fire(
          "Missing Marital Status",
          "Please select marital status.",
          "warning",
        );
      }

      // Contact Number validation
      if (!contactNumber) {
        return Swal.fire(
          "Missing Contact Number",
          "Please enter a contact number.",
          "warning",
        );
      }
      if (!/^09\d{9}$/.test(contactNumber)) {
        return Swal.fire(
          "Invalid Contact Number",
          "Contact number must be 11 digits and start with 09.",
          "warning",
        );
      }

      // Biometric Number validation: optional, but if provided must be exactly 7 digits
      if (biometricNumber && !/^\d{7}$/.test(biometricNumber)) {
        return Swal.fire(
          "Invalid Biometric Number",
          "Biometric number must be exactly 7 digits, or left blank.",
          "warning",
        );
      }

      // Categories validation: must be "ALL" or at least one selected
      if (!categories) {
        return Swal.fire(
          "Missing Categories",
          "Please select at least one category, or leave All checked.",
          "warning",
        );
      }

      // Address validation
      if (!provinceCode || !municipalityCode || !barangayCode || !street) {
        return Swal.fire(
          "Missing Address",
          "Please complete Province, Municipality, Barangay, and Street.",
          "warning",
        );
      }

      // Birthday validation
      if (!birthday) {
        return Swal.fire(
          "Missing Birthday",
          "Please select birthday.",
          "warning",
        );
      }

      // Agency validation
      if (!agency) {
        return Swal.fire("Missing Agency", "Please select agency.", "warning");
      }

      // Optional: prevent future birthday
      const todayDate = new Date().toISOString().split("T")[0];
      if (birthday > todayDate) {
        return Swal.fire(
          "Invalid Birthday",
          "Birthday cannot be in the future.",
          "error",
        );
      }

      // Set max = today
      const today = new Date().toISOString().split("T")[0];
      dateHiredInput.setAttribute("max", today);

      // convert empty strings to null
      const startDate = startDateInput.value ? startDateInput.value : null;
      const endDate = endDateInput.value ? endDateInput.value : null;

      // start_date is now required regardless of employment status
      if (!startDate) {
        return Swal.fire(
          "Missing Start Date",
          "Start date is required.",
          "warning",
        );
      }

      if (statusType === "SEASONAL" || statusType === "RELIEVER") {
        if (!endDate) {
          return Swal.fire(
            "Missing End Date",
            "End date is required for Seasonal/Reliever employment.",
            "warning",
          );
        }

        if (new Date(startDate) > new Date(endDate)) {
          return Swal.fire(
            "Invalid Dates",
            "End date must be after start date.",
            "error",
          );
        }
      }

      const dateHiredValue = dateHiredInput.value;

      if (dateHiredValue) {
        const today = new Date().toISOString().split("T")[0];

        if (dateHiredValue > today) {
          return Swal.fire(
            "Invalid Date Hired",
            "Date hired cannot be in the future.",
            "error",
          );
        }
      }

      // Gather all branches (main + roving)
      let branchesToCheck = branch ? [branch] : [];
      if (sub === "MULTI BRANCH") {
        const rovingBranches = Array.from(
          rovingContainer.querySelectorAll(".roving-select"),
        ).map((s) => s.value);
        if (
          rovingBranches.includes("") ||
          new Set(rovingBranches).size !== rovingBranches.length
        ) {
          const msg = rovingBranches.includes("")
            ? "Please select all roving branches."
            : "Duplicate branches are not allowed.";
          return Swal.fire("Roving Branch Error", msg, "error");
        }
        rovingBranches.forEach((b) => formData.append("roving_branches[]", b));
        branchesToCheck.push(...rovingBranches);
      }

      let multiBrands = [];

      if (sub === "MULTI BRAND") {
        multiBrands = Array.from(
          multiBrandContainer.querySelectorAll(".multi-brand-select"),
        ).map((s) => s.value);

        if (
          multiBrands.includes("") ||
          new Set(multiBrands).size !== multiBrands.length
        ) {
          const msg = multiBrands.includes("")
            ? "Please select all brands."
            : "Duplicate brands are not allowed.";
          return Swal.fire("Multi Brand Error", msg, "error");
        }

        multiBrands.forEach((b) => formData.append("multi_brands[]", b));
      }

      branchesToCheck = [...new Set(branchesToCheck)];

      if (sub === "MULTI BRAND") {
        for (let b of multiBrands) {
          const combo = branchBrandPairs.find(
            (p) => p.branch_code === branch && p.brand_name === b,
          );
          if (!combo || combo.assigned_count >= combo.required_count) {
            return Swal.fire(
              "Cannot Save",
              `Invalid: ${branch} & ${b}`,
              "error",
            );
          }
        }
      }

      // Client-side check: prevent saving full branch/brand combos
      for (let b of branchesToCheck) {
        const combo = branchBrandPairs.find(
          (p) => p.branch_code === b && p.brand_name === brand,
        );
        if (!combo || combo.assigned_count >= combo.required_count) {
          return Swal.fire(
            "Cannot Save",
            `Branch & Brand Invalid: ${b} & ${brand}. Choose another.`,
            "error",
          );
        }
      }

      // =========================
      // DUPLICATE + BLACKLIST CHECK
      // =========================

      // adjust these depending on your actual inputs
      const firstName =
        form.querySelector('input[name="first_name"]')?.value || "";

      const middleName = noMiddleName.checked
        ? ""
        : (form.querySelector('input[name="middle_name"]')?.value || "").trim();

      const lastName =
        form.querySelector('input[name="last_name"]')?.value || "";

      const birthdayValue = birthdayInput.value;

      // only require middle name IF toggle is NOT checked
      if (!firstName || !lastName || !birthdayValue) {
        return Swal.fire(
          "Missing Data",
          "First name, last name, and birthday are required.",
          "warning",
        );
      }

      // optional: only enforce middle name when NOT disabled
      if (!noMiddleName.checked && !middleName) {
        return Swal.fire("Missing Data", "Middle name is required.", "warning");
      }

      try {
        const checkRes = await fetch("functions/check_employee_duplicate.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            first_name: firstName,
            middle_name:
              noMiddleName.checked || !middleName ? null : middleName,
            last_name: lastName,
            birthday: birthdayValue,
            gender: gender,
            marital_status: maritalStatus,
          }),
        });

        const checkData = await checkRes.json();

        const blockedReasons = ["BLACKLISTED / AWOL / TERMINATED", "DECEASED"];

        // expected response shapes:
        // exact match:    { exists: true, source: "employee_info" | "blacklisted", ... }
        // maiden-name match: { exists: false, possible_match: true, source: "...", ... }

        // =========================
        // Exact blacklist match -> hard block, no reassign path
        // =========================
        if (
          checkData &&
          checkData.exists === true &&
          checkData.source === "blacklisted"
        ) {
          return Swal.fire(
            "Cannot Add Employee",
            "This person is on the blacklist. Adding is not allowed.",
            "error",
          );
        }

        // =========================
        // Possible maiden-name match (MARRIED + FEMALE cross-check)
        // =========================
        if (checkData && checkData.possible_match === true) {
          // NEW: hard block before asking for confirmation
          const crossReason = (checkData.reason_for_update || "").toUpperCase();

          if (
            checkData.source === "blacklisted" ||
            blockedReasons.includes(crossReason)
          ) {
            return Swal.fire(
              "Cannot Add Employee",
              checkData.source === "blacklisted"
                ? "This person is on the blacklist. Adding is not allowed."
                : `This employee is ${crossReason}. Adding is not allowed.`,
              "error",
            );
          }
          const result = await Swal.fire({
            icon: "question",
            title: "Possible Match Found",
            html: `Found a record with the same first name and birthday, where the existing last name (<b>${checkData.matched_last_name}</b>) matches the middle name you entered (<b>${checkData.matched_middle_name || middleName}</b>). This may be the same person under a maiden name.<br><br>Is this the same person?`,
            showCancelButton: true,
            confirmButtonText: "Yes, same person",
            cancelButtonText: "No, different person",
          });

          if (result.isConfirmed) {
            if (checkData.source === "blacklisted") {
              return Swal.fire(
                "Cannot Add Employee",
                "This person is on the blacklist. Adding is not allowed.",
                "error",
              );
            }

            // Confirmed same person, matched via employee_info ->
            // treat exactly like a normal duplicate from here on
            const employeeId = checkData.employee_id;
            const id = checkData.id;
            const status = (checkData.status || "").toUpperCase();
            const reason = (checkData.reason_for_update || "").toUpperCase(); // NEW

            if (!employeeId) {
              return Swal.fire(
                "Error",
                "Match confirmed but employee ID is missing.",
                "error",
              );
            }

            if (status === "INACTIVE") {
              const reassignResult = await Swal.fire({
                icon: "question",
                title: "Duplicate Record Found",
                html: "This employee already exists but is currently <b>inactive</b>. Would you like to <b>reassign and overwrite</b> the existing record?",
                showCancelButton: true,
                confirmButtonText: "Yes",
                cancelButtonText: "Cancel",
              });

              if (!reassignResult.isConfirmed) return;

              formData.set("reassign", "1");
              formData.set("employee_id", employeeId);
            } else {
              const openResult = await Swal.fire({
                icon: "info",
                title: "Duplicate Record Found",
                html: "This employee already exists but is currently <b>active</b>. Would you like to <b>open</b> the existing record?",
                showCancelButton: true,
                confirmButtonText: "Yes",
                cancelButtonText: "Cancel",
              });

              if (!openResult.isConfirmed) return;

              window.location.href = `promodizers.php?edit=${id}`;
              return;
            }
          }
          // If not confirmed, fall through and continue as a genuinely new employee
        } else if (checkData && checkData.exists === true) {
          // =========================
          // Exact employee_info match (existing behavior, unchanged)
          // =========================
          const reason = (checkData.reason_for_update || "").toUpperCase();
          const employeeId = checkData.employee_id;
          const id = checkData.id;
          const status = (checkData.status || "").toUpperCase();

          if (!employeeId) {
            return Swal.fire(
              "Error",
              "Duplicate detected but employee ID is missing.",
              "error",
            );
          }

          const $blockedReasons = [
            "BLACKLISTED / AWOL / TERMINATED",
            "DECEASED",
          ];

          // Hard block
          if (blockedReasons.includes(reason)) {
            return Swal.fire(
              "Cannot Add Employee",
              `This employee is ${reason}. Adding is not allowed.`,
              "error",
            );
          }

          // INACTIVE -> offer reassign (NO redirect)
          if (status === "INACTIVE") {
            const result = await Swal.fire({
              icon: "question",
              title: "Duplicate Record Found",
              html: "This employee already exists but is currently <b>inactive</b>. Would you like to <b>reassign and overwrite</b> the existing record?",
              showCancelButton: true,
              confirmButtonText: "Yes",
              cancelButtonText: "Cancel",
            });

            if (!result.isConfirmed) return;

            // mark for reassignment (IMPORTANT)
            formData.set("reassign", "1");
            formData.set("employee_id", employeeId);
          } else {
            // ACTIVE -> open instead
            const result = await Swal.fire({
              icon: "info",
              title: "Duplicate Record Found",
              html: "This employee already exists but is currently <b>active</b>. Would you like to <b>open</b> the existing record?",
              showCancelButton: true,
              confirmButtonText: "Yes",
              cancelButtonText: "Cancel",
            });

            if (!result.isConfirmed) return;

            window.location.href = `promodizers.php?edit=${id}`;
            return;
          }
        }
      } catch (err) {
        console.error(err);
        return Swal.fire("Error", "Failed to validate employee.", "error");
      }

      try {
        // All new employees are now saved as PENDING regardless of
        // branch/brand assignment or seasonal start date.
        formData.set("status", "PENDING");

        formData.set("employment_status", statusType);
        formData.set("assigned_by", window.currentUser || "SYSTEM");
        formData.set("updated_by", window.currentUser || "SYSTEM");
        formData.set("gender", gender);
        formData.set("birthday", birthday);
        formData.set("agency", agency);
        formData.set("marital_status", maritalStatus);
        formData.set("contact_number", contactNumber);

        // Biometric number is optional — send NULL (omit key) when blank
        if (biometricNumber) {
          formData.set("biometric_number", biometricNumber);
        } else {
          formData.delete("biometric_number");
        }

        formData.set("categories", categories); // "ALL" or "TV,DA,..."

        // Address: send both codes and human-readable names.
        // Adjust to formData.set("province", provinceName) etc. if
        // add_employee.php stores address as plain text rather than codes.
        formData.set("province", provinceCode);
        formData.set("province_name", provinceName);
        formData.set("municipality", municipalityCode);
        formData.set("municipality_name", municipalityName);
        formData.set("barangay", barangayCode);
        formData.set("barangay_name", barangayName);
        formData.set("street", street);

        const res = await fetch("functions/add_employee.php", {
          method: "POST",
          body: formData,
        });
        const data = await res.json();

        if (data.status === "success") {
          Swal.fire("Employee Added!", "", "success").then(() => {
            form.reset();
            dateRangeFields.classList.add("d-none");
            rovingField.classList.add("d-none");
            remarksCount.textContent = "0 / 100";
            rovingContainer
              .querySelectorAll(".roving-row")
              .forEach((row, idx) => {
                if (idx > 0) row.remove();
              });
            rovingContainer.querySelector("select").value = "";

            // reset categories dropdown back to "All"
            catAll.checked = true;
            catItems.forEach((cb) => {
              cb.checked = false;
              cb.disabled = true;
            });
            updateCategoriesInputAndLabel();

            // reset address cascade back to province-only state
            municipalityInput.innerHTML =
              '<option value="" disabled selected>-- Select Municipality --</option>';
            barangayInput.innerHTML =
              '<option value="" disabled selected>-- Select Barangay --</option>';
            municipalityInput.disabled = true;
            barangayInput.disabled = true;
            provinceNameInput.value = "";
            municipalityNameInput.value = "";
            barangayNameInput.value = "";

            bootstrap.Modal.getInstance(
              document.getElementById("addEmployeeModal"),
            ).hide();
            window.location.href = "promodizers.php";
            window.assignmentTable?.ajax.reload(null, false);
          });
        } else {
          Swal.fire("Oops...", data.message, "error");
        }
      } catch (err) {
        console.error(err);
        Swal.fire(
          "Error!",
          "An unexpected error occurred. Try again.",
          "error",
        );
      }
    } finally {
      // ✅ Runs on every exit path: validation returns, duplicate-check
      // returns, the "open existing record" redirect, success, and errors.
      clearTimeout(slowConnTimer);
      form.dataset.submitting = "0";
      btn.disabled = false;
      btn.textContent = originalBtnText;
      dismissEls.forEach((b) => (b.disabled = false));
      modalEl.removeEventListener("hide.bs.modal", blockHide);
    }
  });
});
