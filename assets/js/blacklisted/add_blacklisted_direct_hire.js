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
  // Includes #bldh_employment_status (labelled "Position" in the UI).
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
      // Stored as employment_status, shown to the user as "Position"
      employment_status: $("#bldh_employment_status").val().trim(),
      end_date: $("#bldh_end_date").val(),
      remarks: $("#bldh_remarks").val().trim(),
    };
  }

  function refreshBlacklistedTable() {
    if (window.blacklistedTable) {
      window.blacklistedTable.ajax.reload(null, false);
    } else {
      location.reload();
    }
  }

  // After the blacklist record is saved, ask whether to deactivate the matched user
  function askDeactivateUser(matchedUser) {
    const matchedName = [
      matchedUser.first_name,
      matchedUser.middle_name,
      matchedUser.last_name,
    ]
      .filter(Boolean)
      .join(" ");

    Swal.fire({
      icon: "question",
      title: "Make User Inactive?",
      html: `A user account matches this blacklisted person:<br><b>${matchedName}</b> (${matchedUser.username})<br>Position: ${matchedUser.position || "-"} &nbsp; Branch: ${matchedUser.branch || "-"}<br><br>Do you want to mark this user as <b>INACTIVE</b>?`,
      showCancelButton: true,
      confirmButtonText: "Yes, Make Inactive",
      cancelButtonText: "No, Keep Active",
      showLoaderOnConfirm: true,
      allowOutsideClick: () => !Swal.isLoading(),
      preConfirm: () => {
        return $.ajax({
          url: "functions/deactivate_user.php",
          method: "POST",
          contentType: "application/json",
          data: JSON.stringify({ user_id: matchedUser.id }),
          dataType: "json",
        })
          .then((res) => {
            if (!res.success) {
              throw new Error(res.message || "Failed to deactivate user.");
            }
            return res;
          })
          .catch((xhr) => {
            const msg =
              xhr && xhr.message ? xhr.message : getAjaxErrorMessage(xhr);
            Swal.showValidationMessage(msg);
          });
      },
    }).then((result) => {
      if (result.isConfirmed && result.value) {
        Swal.fire({
          icon: "success",
          title: "User Deactivated",
          text: "The user has been marked as INACTIVE.",
          timer: 1800,
          showConfirmButton: false,
        }).then(refreshBlacklistedTable);
      } else {
        refreshBlacklistedTable();
      }
    });
  }

  function submitBlacklisted(payload, matchedUser) {
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

          const userIsActive =
            matchedUser &&
            String(matchedUser.status || "").toUpperCase() !== "INACTIVE";

          if (userIsActive) {
            askDeactivateUser(matchedUser);
            return;
          }

          Swal.fire({
            icon: "success",
            title: "Added",
            text: "Blacklisted record has been added successfully.",
            timer: 1800,
            showConfirmButton: false,
          }).then(refreshBlacklistedTable);
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

    // Direct Hire match is checked against the `users` table
    // by first name, middle name, and last name only.
    $.ajax({
      url: "functions/check_blacklisted_direct_hire_match.php",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        first_name: payload.first_name,
        middle_name: payload.middle_name,
        last_name: payload.last_name,
      }),
      dataType: "json",
    })
      .done(function (res) {
        $("#saveBlacklistedDirectHireBtn").prop("disabled", false).text("Save");

        if (!res.success) {
          Swal.fire(
            "Error",
            res.message || "Unable to check for a matching user.",
            "error",
          );
          return;
        }

        // Save the blacklist record first; if a user matched, the
        // "Make User Inactive?" prompt appears after a successful save.
        submitBlacklisted(payload, res.match || null);
      })
      .fail(function (xhr) {
        $("#saveBlacklistedDirectHireBtn").prop("disabled", false).text("Save");
        Swal.fire("Error", getAjaxErrorMessage(xhr), "error");
      });
  });
});
