$(document).ready(function () {
  // =========================
  // SHARED ERROR HELPER
  // =========================
  function getAjaxErrorMessage(xhr) {
    if (xhr.responseJSON && xhr.responseJSON.message) {
      return xhr.responseJSON.message;
    }

    if (xhr.responseText) {
      try {
        const parsed = JSON.parse(xhr.responseText);
        if (parsed.message) return parsed.message;
      } catch (e) {
        const text = xhr.responseText.replace(/<[^>]*>/g, "").trim();
        if (text) return text.substring(0, 500);
      }
    }

    return `Something went wrong (HTTP ${xhr.status || "unknown"}).`;
  }

  let selectedEmployee = null;

  // ---------------------------------------------------------------
  // Branch dropdown — same source as the Add Employee modal
  // (functions/get_available_branches_brands.php), promodiser
  // branches only, no HEAD OFFICE / TECHNOFLEX here.
  // ---------------------------------------------------------------
  function populateBranchDropdown() {
    const $select = $("#blp_branch");
    $select
      .html('<option value="" selected disabled>Loading branches...</option>')
      .prop("disabled", true);

    fetch("functions/get_available_branches_brands.php")
      .then((r) => r.json())
      .then((pairs) => {
        pairs = pairs || [];
        const uniqueBranches = [...new Set(pairs.map((p) => p.branch_code))];

        $select.html(
          '<option value="" selected disabled>Select Branch</option>',
        );

        if (!uniqueBranches.length) {
          $select.append('<option value="">No branches available</option>');
          $select.prop("disabled", true);
          return;
        }

        const options = uniqueBranches
          .map((code) => ({
            value: code,
            label:
              pairs.find((p) => p.branch_code === code)?.branch_name || code,
          }))
          .sort((a, b) => a.label.localeCompare(b.label));

        options.forEach((opt) =>
          $select.append(`<option value="${opt.value}">${opt.label}</option>`),
        );
        $select.prop("disabled", false);
      })
      .catch(() => {
        $select
          .html('<option value="">Failed to load branches</option>')
          .prop("disabled", true);
      });
  }

  function loadEmployeesForBranch(branchCode) {
    const $empSelect = $("#blp_employee_select")
      .empty()
      .append('<option value="">Loading...</option>')
      .prop("disabled", true);

    fetch(
      "functions/fetch_branch_employees.php?branch=" +
        encodeURIComponent(branchCode),
    )
      .then((r) => r.json())
      .then((results) => {
        $empSelect.empty();

        if (results.error) {
          $empSelect.append(`<option value="">${results.error}</option>`);
          return;
        }
        if (!results.length) {
          $empSelect.append(
            '<option value="">No employees found for this branch</option>',
          );
          return;
        }

        $empSelect.append('<option value="">Select promodiser...</option>');
        results.forEach((emp) => {
          $empSelect.append(
            `<option value="${emp.employee_id}">${emp.first_name} ${emp.last_name}</option>`,
          );
        });
        $empSelect.prop("disabled", false);
        $empSelect.data("employees", results);
      })
      .catch(() => {
        $empSelect
          .empty()
          .append('<option value="">Failed to load employees</option>');
      });
  }

  function clearEmployeeFields() {
    selectedEmployee = null;
    [
      "first_name",
      "middle_name",
      "last_name",
      "suffix",
      "birthdate",
      "gender",
      "marital_status",
      "branch_display",
      "brand",
      "employment_status",
    ].forEach((f) => $("#blp_" + f).val(""));
    $("#blp_branch_code").val("");
    $("#blp_employee_id").val("");
    $("#saveBlacklistedPromodiserBtn").prop("disabled", true);
  }

  function selectEmployee(emp) {
    selectedEmployee = emp;

    $("#blp_employee_id").val(emp.employee_id);
    $("#blp_first_name").val(emp.first_name);
    $("#blp_middle_name").val(emp.middle_name);
    $("#blp_last_name").val(emp.last_name);
    $("#blp_suffix").val(emp.suffix);
    $("#blp_birthdate").val(emp.birthday ? emp.birthday.split("T")[0] : "");
    $("#blp_gender").val(emp.gender);
    $("#blp_marital_status").val(emp.marital_status);
    $("#blp_branch_display").val(emp.branch); // display name
    $("#blp_branch_code").val(emp.branch_code); // actual value submitted/stored
    $("#blp_brand").val(emp.brand);
    $("#blp_employment_status").val(emp.employment_status);

    $("#saveBlacklistedPromodiserBtn").prop("disabled", false);
  }

  function resetForm() {
    selectedEmployee = null;
    $("#blp_employee_select")
      .empty()
      .append('<option value="">Select a branch first...</option>')
      .prop("disabled", true);
    clearEmployeeFields();
    $("#blp_end_date").val("");
    $("#blp_remarks").val("");
    $("#blp_remarks_count").text("0");
  }

  // ---- Open modal ----
  $("#addBlacklistedPromodiserBtn").on("click", function () {
    resetForm();
    populateBranchDropdown();

    const today = new Date().toISOString().split("T")[0];
    $("#blp_end_date").attr("max", today);

    $("#addBlacklistedPromodiserModal").modal("show");
  });

  $("#blp_branch").on("change", function () {
    const branchCode = $(this).val();
    clearEmployeeFields();

    if (branchCode) {
      loadEmployeesForBranch(branchCode);
    } else {
      $("#blp_employee_select")
        .empty()
        .append('<option value="">Select a branch first...</option>')
        .prop("disabled", true);
    }
  });

  $("#blp_employee_select").on("change", function () {
    const employeeId = $(this).val();
    const employees = $(this).data("employees") || [];
    const emp = employees.find(
      (e) => String(e.employee_id) === String(employeeId),
    );

    if (!emp) {
      clearEmployeeFields();
      return;
    }
    selectEmployee(emp);
  });

  // ---- Remarks character counter ----
  $("#blp_remarks").on("input", function () {
    $("#blp_remarks_count").text($(this).val().length);
  });

  function buildPayload() {
    return {
      first_name: $("#blp_first_name").val().trim(),
      middle_name: $("#blp_middle_name").val().trim(),
      last_name: $("#blp_last_name").val().trim(),
      suffix: ($("#blp_suffix").val() || "").trim(),
      gender: $("#blp_gender").val(),
      birthdate: $("#blp_birthdate").val(),
      marital_status: $("#blp_marital_status").val(),
      branch: $("#blp_branch_code").val(),
      brand: $("#blp_brand").val(),
      employment_status: $("#blp_employment_status").val(),
      end_date: $("#blp_end_date").val(),
      remarks: $("#blp_remarks").val().trim(),
    };
  }

  function submitBlacklisted(payload) {
    $("#saveBlacklistedPromodiserBtn").prop("disabled", true).text("Saving...");

    $.ajax({
      url: "functions/insert_blacklisted.php",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify(payload),
      dataType: "json",
    })
      .done(function (res) {
        if (res.success) {
          $("#addBlacklistedPromodiserModal").modal("hide");
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
      .fail(function (xhr) {
        Swal.fire("Error", getAjaxErrorMessage(xhr), "error");
      })
      .always(function () {
        $("#saveBlacklistedPromodiserBtn").prop("disabled", false).text("Save");
      });
  }

  // ---- Save ----
  $("#saveBlacklistedPromodiserBtn").on("click", function () {
    if (!selectedEmployee) return;

    if (!$("#blp_end_date").val()) {
      Swal.fire("End date required", "Please provide an end date.", "warning");
      return;
    }
    if (!$("#blp_remarks").val().trim()) {
      Swal.fire(
        "Remarks required",
        "Please provide a reason for this blacklist entry.",
        "warning",
      );
      return;
    }

    const $btn = $(this);
    if ($btn.prop("disabled")) return; // already submitting — ignore extra clicks

    const payload = buildPayload();

    $btn.prop("disabled", true).text("Checking...");

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
        $btn.prop("disabled", false).text("Save");

        if (!res.success) {
          Swal.fire(
            "Error",
            res.message || "Unable to check for a matching employee.",
            "error",
          );
          return;
        }

        if (!res.match) {
          submitBlacklisted(payload);
          return;
        }

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
        });
      })
      .fail(function (xhr) {
        $btn.prop("disabled", false).text("Save");
        Swal.fire("Error", getAjaxErrorMessage(xhr), "error");
      });
  });
});
