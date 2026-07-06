$(document).ready(function () {
  // ---- Branch / Brand data (same source as the Add Employee modal) ----
  // [{branch_code, branch_name, brand_name, required_count, assigned_count}]
  let branchBrandPairs = [];

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

  $("#bl_branch").on("change", updateBrandSelect);

  // ---- Uppercase all text inputs / textarea as the user types ----
  $("#addBlacklistedForm")
    .find('input[type="text"], textarea')
    .on("input", function () {
      const cursor = this.selectionStart;
      this.value = this.value.toUpperCase();
      this.setSelectionRange(cursor, cursor);
    });

  // ---- Open modal ----
  $("#addBlacklistedBtn").on("click", async function () {
    $("#addBlacklistedForm")[0].reset();
    $("#bl_remarks_count").text("0");
    $("#bl_direct_hire").prop("checked", false);
    resetDirectHireLock();
    $("#bl_no_middle_name").prop("checked", false);
    $("#bl_middle_name").prop("disabled", false).prop("required", true);

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

  // ---- Direct Hire checkbox behavior ----
  const DIRECT_HIRE_VALUE = "Direct Hired";

  $("#bl_direct_hire").on("change", function () {
    if ($(this).is(":checked")) {
      lockAsDirectHire();
    } else {
      resetDirectHireLock();
    }
  });

  function lockAsDirectHire() {
    const $brand = $("#bl_brand");
    const $status = $("#bl_employment_status");

    if ($brand.find(`option[value="${DIRECT_HIRE_VALUE}"]`).length === 0) {
      $brand.append(
        `<option value="${DIRECT_HIRE_VALUE}">${DIRECT_HIRE_VALUE}</option>`,
      );
    }
    $brand.val(DIRECT_HIRE_VALUE).prop("disabled", true);

    if ($status.find(`option[value="${DIRECT_HIRE_VALUE}"]`).length === 0) {
      $status.append(
        `<option value="${DIRECT_HIRE_VALUE}">${DIRECT_HIRE_VALUE}</option>`,
      );
    }
    $status.val(DIRECT_HIRE_VALUE).prop("disabled", true);
  }

  function resetDirectHireLock() {
    const $brand = $("#bl_brand");
    const $status = $("#bl_employment_status");

    $brand.find(`option[value="${DIRECT_HIRE_VALUE}"]`).remove();
    $status.find(`option[value="${DIRECT_HIRE_VALUE}"]`).remove();

    $brand.prop("disabled", false).val("");
    $status.prop("disabled", false).val("");
  }

  // ---- Save ----
  $("#saveBlacklistedBtn").on("click", function () {
    const form = document.getElementById("addBlacklistedForm");

    // Native validation, but skip disabled fields (they're locked via Direct Hire)
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const payload = {
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
      brand: $("#bl_brand").val(),
      employment_status: $("#bl_employment_status").val(),
      end_date: $("#bl_end_date").val(),
      remarks: $("#bl_remarks").val().trim(),
    };

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
          });
          // Adjust to match your DataTable variable name in blacklisted.js
          if (typeof blacklistedTable !== "undefined") {
            blacklistedTable.ajax.reload(null, false);
          }
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
  });
});
