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

  // ---------------------------------------------------------------
  // Branch dropdown — promodiser branches (get_available_branches_brands.php)
  // plus the two Direct-Hire-only pseudo-branches.
  // ---------------------------------------------------------------
  function populateBranchSelect() {
    const $branch = $("#bldh_branch");
    $branch
      .html('<option value="" selected disabled>Loading...</option>')
      .prop("disabled", true);

    fetch("functions/get_available_branches_brands.php")
      .then((r) => r.json())
      .then((pairs) => {
        pairs = pairs || [];
        const uniqueBranches = [...new Set(pairs.map((p) => p.branch_code))];

        let options = uniqueBranches.map((code) => ({
          value: code,
          label: pairs.find((p) => p.branch_code === code)?.branch_name || code,
        }));

        options.push({ value: "HEAD OFFICE", label: "HEAD OFFICE" });
        options.push({ value: "TECHNOFLEX", label: "TECHNOFLEX" });

        options.sort((a, b) => a.label.localeCompare(b.label));

        $branch.html(
          '<option value="" selected disabled>Select Branch</option>',
        );
        options.forEach((opt) =>
          $branch.append(`<option value="${opt.value}">${opt.label}</option>`),
        );
        $branch.prop("disabled", false);
      })
      .catch(() => {
        $branch
          .html('<option value="">Failed to load branches</option>')
          .prop("disabled", true);
      });
  }

  // ---- Uppercase all text inputs as the user types (remarks excluded) ----
  $("#addBlacklistedDirectHireForm")
    .find('input[type="text"], textarea')
    .not("#bldh_remarks")
    .on("input", function () {
      const cursor = this.selectionStart;
      this.value = this.value.toUpperCase();
      this.setSelectionRange(cursor, cursor);
    });

  // ---- Open modal ----
  $("#addBlacklistedDirectHireBtn").on("click", function () {
    $("#addBlacklistedDirectHireForm")[0].reset();
    $("#bldh_remarks_count").text("0");
    $("#bldh_no_middle_name").prop("checked", false);
    $("#bldh_middle_name").prop("disabled", false).prop("required", true);

    populateBranchSelect();

    // End date (and birthdate) cannot be beyond today
    const today = new Date().toISOString().split("T")[0];
    $("#bldh_end_date").attr("max", today);
    $("#bldh_birthdate").attr("max", today);

    $("#addBlacklistedDirectHireModal").modal("show");
  });

  // ---- No Middle Name checkbox ----
  $("#bldh_no_middle_name").on("change", function () {
    const $middleName = $("#bldh_middle_name");
    if (this.checked) {
      $middleName.val("").prop("disabled", true).prop("required", false);
    } else {
      $middleName.prop("disabled", false).prop("required", true);
    }
  });

  // ---- Remarks character counter ----
  $("#bldh_remarks").on("input", function () {
    $("#bldh_remarks_count").text($(this).val().length);
  });

  function buildPayload() {
    return {
      first_name: $("#bldh_first_name").val().trim(),
      middle_name: $("#bldh_no_middle_name").is(":checked")
        ? ""
        : $("#bldh_middle_name").val().trim(),
      last_name: $("#bldh_last_name").val().trim(),
      suffix: $("#bldh_suffix").val().trim(),
      gender: $("#bldh_gender").val(),
      birthdate: $("#bldh_birthdate").val(),
      marital_status: $("#bldh_marital_status").val(),
      branch: $("#bldh_branch").val(),
      brand: "DIRECT HIRE",
      employment_status: "",
      end_date: $("#bldh_end_date").val(),
      remarks: $("#bldh_remarks").val().trim(),
    };
  }

  function submitBlacklisted(payload) {
    $("#saveBlacklistedDirectHireBtn").prop("disabled", true).text("Saving...");

    $.ajax({
      url: "functions/insert_blacklisted.php",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify(payload),
      dataType: "json",
    })
      .done(function (res) {
        if (res.success) {
          $("#addBlacklistedDirectHireModal").modal("hide");
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
        $("#saveBlacklistedDirectHireBtn").prop("disabled", false).text("Save");
      });
  }

  // ---- Save ----
  $("#saveBlacklistedDirectHireBtn").on("click", function () {
    const form = document.getElementById("addBlacklistedDirectHireForm");

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const payload = buildPayload();

    $("#saveBlacklistedDirectHireBtn")
      .prop("disabled", true)
      .text("Checking...");

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
        $("#saveBlacklistedDirectHireBtn").prop("disabled", false).text("Save");

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
          // If cancelled: do nothing — modal stays open, no reload, user can edit fields.
        });
      })
      .fail(function (xhr) {
        $("#saveBlacklistedDirectHireBtn").prop("disabled", false).text("Save");
        Swal.fire("Error", getAjaxErrorMessage(xhr), "error");
      });
  });
});
