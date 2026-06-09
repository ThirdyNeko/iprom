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

    // Reset
    dateRangeFields.classList.add("d-none");
    rovingField.classList.add("d-none");

    dateRangeFields
      .querySelectorAll("input")
      .forEach((i) => (i.required = false));
    rovingField
      .querySelectorAll(".roving-select")
      .forEach((s) => (s.required = false));

    // Show date range for seasonal/reliever
    if (empStatus === "SEASONAL" || empStatus === "RELIEVER") {
      dateRangeFields.classList.remove("d-none");
      dateRangeFields
        .querySelectorAll("input")
        .forEach((i) => (i.required = true));
    }

    // ✅ NEW: Show roving when MULTI BRANCH
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

      const opt = new Option(displayName, code); // 👈 label = name, value = code

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

      // ❌ exclude main branch (by code)
      if (code === currentBranch) return;

      // ❌ exclude already selected
      if (selectedBranches.includes(code)) return;

      const opt = new Option(displayName, code); // 👈 label=name, value=code

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
      // ✅ IMPORTANT FIX: use passed value, not global only
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

      // ✅ refresh ALL roving selects immediately
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

    const formData = new FormData(form);

    let reassignEmployeeId = null;

    // Handle optional middle name & suffix (send NULL instead of empty)
    if (!formData.get("middle_name")) formData.delete("middle_name");
    if (!formData.get("suffix")) formData.delete("suffix");

    // Start / End date validation
    const startDateInput = form.querySelector('input[name="start_date"]');
    const endDateInput = form.querySelector('input[name="end_date"]');
    // ✅ FIX: remove empty date fields so PHP gets NULL
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

    // Gender validation
    if (!gender) {
      return Swal.fire("Missing Gender", "Please select gender.", "warning");
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
    if (statusType === "SEASONAL" || statusType === "RELIEVER") {
      if (!startDate || !endDate) {
        return Swal.fire(
          "Missing Dates",
          "Start and End dates are required.",
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
          return Swal.fire("Cannot Save", `Invalid: ${branch} & ${b}`, "error");
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

    const lastName = form.querySelector('input[name="last_name"]')?.value || "";

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
          middle_name: noMiddleName.checked || !middleName ? null : middleName,
          last_name: lastName,
          birthday: birthdayValue,
        }),
      });

      const checkData = await checkRes.json();

      // expected response example:
      // { exists: true, reason_for_update: "BLACKLISTED" }

      const blockedReasons = ["BLACKLISTED / AWOL / TERMINATED"];

      if (checkData && checkData.exists === true) {
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

        const blockedReasons = ["BLACKLISTED / AWOL / TERMINATED"];

        // 🚫 Hard block
        if (blockedReasons.includes(reason)) {
          return Swal.fire(
            "Cannot Add Employee",
            `This employee is ${reason}. Adding is not allowed.`,
            "error",
          );
        }

        // 🟡 INACTIVE → offer reassign (NO redirect)
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

          // ✅ mark for reassignment (IMPORTANT)
          formData.set("reassign", "1");
          formData.set("employee_id", employeeId);
        } else {
          // 🟢 ACTIVE → open instead
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

    // // Confirmation
    // const confirm = await Swal.fire({
    //   icon: "warning",
    //   title: "Are you sure?",
    //   showCancelButton: true,
    //   confirmButtonText: "Yes, Save",
    //   cancelButtonText: "Cancel",
    //   confirmButtonColor: "#d33",
    // });
    // if (!confirm.isConfirmed) return;

    try {
      btn.disabled = true;

      let status =
        sub === "MULTI BRANCH" || sub === "MULTI BRAND" || (branch && brand)
          ? "ACTIVE"
          : "INACTIVE";

      // =========================
      // SEASONAL / RELIEVER RULE
      // =========================
      if (
        (statusType === "SEASONAL" || statusType === "RELIEVER") &&
        startDateInput.value
      ) {
        const today = new Date().toISOString().split("T")[0];

        if (today < startDateInput.value) {
          status = "INACTIVE";
        }
      }

      formData.set("status", status);
      formData.set("employment_status", statusType);
      formData.set("assigned_by", window.currentUser || "SYSTEM");
      formData.set("updated_by", window.currentUser || "SYSTEM");
      formData.set("gender", gender);
      formData.set("birthday", birthday);
      formData.set("agency", agency);

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
      Swal.fire("Error!", "An unexpected error occurred. Try again.", "error");
    } finally {
      btn.disabled = false;
    }
  });
});
