$(document).ready(function () {
  // ---- Branch / Brand data (same source as the Add Employee modal) ----
  // [{branch_code, branch_name, brand_name, required_count, assigned_count}]
  let branchBrandPairs = [];

  // Which tab the modal was opened from: 'promodiser' | 'direct_hire'
  let currentCategory = "promodiser";

  async function loadBranchBrandPairs() {
    try {
      const res = await fetch("functions/get_available_branches_brands.php");
      branchBrandPairs = await res.json();
    } catch (err) {
      console.error("Failed to fetch branch-brand data", err);
      branchBrandPairs = [];
    }
  }

  function populateBranchSelect() {
    const $branch = $("#bl_branch");
    const uniqueBranches = [
      ...new Set(branchBrandPairs.map((p) => p.branch_code)),
    ];

    $branch.html('<option value="" disabled selected>Select Branch</option>');

    uniqueBranches.forEach((code) => {
      const displayName =
        branchBrandPairs.find((p) => p.branch_code === code)?.branch_name ||
        code;
      $branch.append(`<option value="${code}">${displayName}</option>`);
    });
  }

  function updateBrandSelect() {
    const branch = $("#bl_branch").val();
    const $brand = $("#bl_brand");

    $brand.html('<option value="" disabled selected>Select Brand</option>');

    branchBrandPairs
      .filter((p) => p.branch_code === branch)
      .forEach((p) => {
        $brand.append(
          `<option value="${p.brand_name}">${p.brand_name}</option>`,
        );
      });
  }

  // ---- Show/hide Brand + Employment Status depending on active tab ----
  function applyCategoryToForm(category) {
    const isDirectHire = category === "direct_hire";

    $("#bl_brand_group").toggle(!isDirectHire);
    $("#bl_employment_status_group").toggle(!isDirectHire);

    $("#bl_brand").prop("required", !isDirectHire);
    $("#bl_employment_status").prop("required", !isDirectHire);
  }

  $("#bl_branch").on("change", updateBrandSelect);

  // ---- Uppercase all text inputs / textarea as the user types (remarks excluded) ----
  $("#addBlacklistedForm")
    .find('input[type="text"], textarea')
    .not("#bl_remarks")
    .on("input", function () {
      const cursor = this.selectionStart;
      this.value = this.value.toUpperCase();
      this.setSelectionRange(cursor, cursor);
    });

  // ---- Open modal ----
  $("#addBlacklistedBtn").on("click", async function () {
    $("#addBlacklistedForm")[0].reset();
    $("#bl_remarks_count").text("0");
    $("#bl_no_middle_name").prop("checked", false);
    $("#bl_middle_name").prop("disabled", false).prop("required", true);

    currentCategory = $("#direct-hire-pane").hasClass("active")
      ? "direct_hire"
      : "promodiser";
    applyCategoryToForm(currentCategory);

    await loadBranchBrandPairs();
    populateBranchSelect();
    updateBrandSelect();

    // End date (and birthdate) cannot be beyond today
    const today = new Date().toISOString().split("T")[0];
    $("#bl_end_date").attr("max", today);
    $("#bl_birthdate").attr("max", today);

    $("#addBlacklistedModal").modal("show");
  });

  // ---- No Middle Name checkbox ----
  $("#bl_no_middle_name").on("change", function () {
    const $middleName = $("#bl_middle_name");
    if (this.checked) {
      $middleName.val("").prop("disabled", true).prop("required", false);
    } else {
      $middleName.prop("disabled", false).prop("required", true);
    }
  });

  // ---- Remarks character counter ----
  $("#bl_remarks").on("input", function () {
    $("#bl_remarks_count").text($(this).val().length);
  });

  function buildPayload() {
    const isDirectHire = currentCategory === "direct_hire";

    return {
      first_name: $("#bl_first_name").val().trim(),
      middle_name: $("#bl_no_middle_name").is(":checked")
        ? ""
        : $("#bl_middle_name").val().trim(),
      last_name: $("#bl_last_name").val().trim(),
      suffix: $("#bl_suffix").val().trim(),
      gender: $("#bl_gender").val(),
      birthdate: $("#bl_birthdate").val(),
      marital_status: $("#bl_marital_status").val(),
      branch: $("#bl_branch").val(),
      brand: isDirectHire ? "DIRECT HIRE" : $("#bl_brand").val(),
      employment_status: isDirectHire ? "" : $("#bl_employment_status").val(),
      end_date: $("#bl_end_date").val(),
      remarks: $("#bl_remarks").val().trim(),
    };
  }

  function submitBlacklisted(payload) {
    $("#saveBlacklistedBtn").prop("disabled", true).text("Saving...");

    $.ajax({
      url: "functions/insert_blacklisted.php",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify(payload),
      dataType: "json",
    })
      .done(function (res) {
        if (res.success) {
          $("#addBlacklistedModal").modal("hide");
          Swal.fire({
            icon: "success",
            title: "Added",
            text: "Blacklisted record has been added successfully.",
            timer: 1800,
            showConfirmButton: false,
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire("Error", res.message || "Failed to add record.", "error");
        }
      })
      .fail(function () {
        Swal.fire("Error", "Something went wrong while saving.", "error");
      })
      .always(function () {
        $("#saveBlacklistedBtn").prop("disabled", false).text("Save");
      });
  }

  // ---- Save ----
  $("#saveBlacklistedBtn").on("click", function () {
    const form = document.getElementById("addBlacklistedForm");

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const payload = buildPayload();

    $("#saveBlacklistedBtn").prop("disabled", true).text("Checking...");

    $.ajax({
      url: "functions/check_blacklisted_match.php",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        first_name: payload.first_name,
        middle_name: payload.middle_name,
        last_name: payload.last_name,
        birthdate: payload.birthdate,
        branch: payload.branch,
        brand: payload.brand,
      }),
      dataType: "json",
    })
      .done(function (res) {
        $("#saveBlacklistedBtn").prop("disabled", false).text("Save");

        if (!res.success) {
          Swal.fire(
            "Error",
            res.message || "Unable to check for a matching employee.",
            "error",
          );
          return;
        }

        if (!res.match) {
          // No match — safe to save directly.
          submitBlacklisted(payload);
          return;
        }

        // Match found — confirm before cascading. Modal stays open either way.
        const m = res.match;
        const matchedName = [m.first_name, m.middle_name, m.last_name]
          .filter(Boolean)
          .join(" ");

        Swal.fire({
          icon: "warning",
          title: "Matching Employee Found",
          html: `This matches an existing employee record:<br><b>${matchedName}</b> (${m.employee_id})<br>Branch: ${m.branch} &nbsp; Brand: ${m.brand}<br><br>Continuing will mark that employee as <b>INACTIVE</b> and cascade this blacklist entry. Continue?`,
          showCancelButton: true,
          confirmButtonText: "Yes, Continue",
          cancelButtonText: "No, Let Me Edit",
        }).then((result) => {
          if (result.isConfirmed) {
            submitBlacklisted(payload);
          }
          // If cancelled: do nothing — modal stays open, no reload, user can edit fields.
        });
      })
      .fail(function () {
        $("#saveBlacklistedBtn").prop("disabled", false).text("Save");
        Swal.fire(
          "Error",
          "Something went wrong while checking for a match.",
          "error",
        );
      });
  });
});
