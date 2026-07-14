// ─────────────────────────────────────────────────────────────
// verify_loa.js
// Handles the "Verify LOA" modal: LOA code check -> ID picture
// check/upload -> status finalization (ACTIVE / QUEUED).
//
// Picture storage note: pictures are stored as binary data in
// [IPROM_TEST].[dbo].[employee_pictures].[id_picture] (not on disk),
// so the backend returns/accepts base64 data URIs (`picture_data`)
// rather than file URLs.
//
// IMPORTANT: everything is wrapped in $(document).ready() below.
// The modal markup (modals/verify_loa_modal.php) is included in the
// page AFTER this <script> tag, so #verifyNextBtn etc. don't exist
// yet at the moment this file first executes. Binding directly at
// the top level (e.g. `$("#verifyNextBtn").on(...)`) would select
// an empty jQuery set and silently attach nothing. Waiting for
// document.ready guarantees the full page HTML — including the
// included modal — has been parsed first.
//
// Requires: jQuery, Bootstrap 5 bundle, SweetAlert2, and the
// DataTable instance `table` from verification.js (for redraw).
// ─────────────────────────────────────────────────────────────

let verifyState = {};
let currentStep = 1;

$(document).ready(function () {
  // Open trigger uses delegation since these buttons come from a
  // DataTable's ajax-rendered rows (added to the DOM after load).
  $(document).on("click", ".verifyLOABtn", function () {
    const btn = $(this);

    verifyState = {
      employeeId: btn.data("employee-id"),
      loaId: btn.data("loa-id"),
      branchCode: btn.data("branch"),
      hasExistingPicture: false,
      overwrite: false,
    };

    // Reset modal UI
    $("#loaCodeInput").val("");
    $("#loaCodeError").addClass("d-none").text("");
    $("#idPictureInput").val("");
    $("#pictureError").addClass("d-none").text("");
    $("#previewWrap").addClass("d-none");
    $("#existingPictureWrap").addClass("d-none");
    $("#uploadWrap").removeClass("d-none");
    $("#uploadPrompt").text("Upload ID Picture");

    goToStep(1);
    new bootstrap.Modal(document.getElementById("verifyLOAModal")).show();
  });

  $("#verifyBackBtn").on("click", function () {
    if (currentStep > 1) goToStep(currentStep - 1);
  });

  $("#verifyNextBtn").on("click", function () {
    if (currentStep === 1) handleStep1();
    else if (currentStep === 2) handleStep2();
  });

  $("#keepExistingBtn").on("click", async function () {
    verifyState.overwrite = false;
    goToStep(3);
    await finalizeVerification();
  });

  $("#overwriteBtn").on("click", function () {
    verifyState.overwrite = true;
    $("#uploadWrap").removeClass("d-none");
    $("#uploadPrompt").text(
      "Upload the new ID picture to overwrite the existing one.",
    );
  });

  $("#idPictureInput").on("change", function () {
    $("#pictureError").addClass("d-none");
    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      $("#previewImg").attr("src", e.target.result);
      $("#previewWrap").removeClass("d-none");
    };
    reader.readAsDataURL(file);
  });
});

function goToStep(step) {
  currentStep = step;
  $(".verify-step").addClass("d-none");
  $(`#verifyStep${step}`).removeClass("d-none");
  $(".step-item").removeClass("active");
  $(`.step-item[data-step="${step}"]`).addClass("active");
  $("#verifyBackBtn").toggleClass("d-none", step === 1 || step === 3);
  $("#verifyNextBtn").toggleClass("d-none", step === 3);
}

// ── STEP 1: Verify LOA code against employee_info ──────────────
async function handleStep1() {
  const code = $("#loaCodeInput").val().trim();
  if (!code) {
    $("#loaCodeError").removeClass("d-none").text("Please enter the LOA code.");
    return;
  }

  $("#verifyNextBtn").prop("disabled", true).text("Checking...");
  try {
    const res = await fetch("functions/verify_loa_code.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        employee_id: verifyState.employeeId,
        loa_code: code,
      }),
    });
    const result = await res.json();

    if (!result.success) {
      $("#loaCodeError")
        .removeClass("d-none")
        .text(result.message || "LOA code does not match our records.");
      return;
    }

    $("#loaCodeError").addClass("d-none");
    await loadExistingPicture();
    goToStep(2);
  } catch (err) {
    console.error("LOA code verification failed:", err);
    Swal.fire("Error", "Unable to verify LOA code. Please try again.", "error");
  } finally {
    $("#verifyNextBtn").prop("disabled", false).text("Next");
  }
}

// ── STEP 2: Check for existing picture, then upload/overwrite ──
async function loadExistingPicture() {
  try {
    const res = await fetch(
      `functions/check_employee_picture.php?employee_id=${encodeURIComponent(verifyState.employeeId)}`,
    );
    const result = await res.json();

    if (result.exists) {
      verifyState.hasExistingPicture = true;
      // result.picture_data is a base64 data URI (e.g. "data:image/jpeg;base64,...")
      $("#existingPictureImg").attr("src", result.picture_data);
      $("#existingPictureWrap").removeClass("d-none");
      $("#uploadWrap").addClass("d-none");
    } else {
      verifyState.hasExistingPicture = false;
      $("#existingPictureWrap").addClass("d-none");
      $("#uploadWrap").removeClass("d-none");
      $("#uploadPrompt").text("No picture on file yet — please upload one.");
    }
  } catch (err) {
    console.error("Picture check failed:", err);
    // Fail safe: treat as no picture on file so the flow isn't blocked
    verifyState.hasExistingPicture = false;
    $("#existingPictureWrap").addClass("d-none");
    $("#uploadWrap").removeClass("d-none");
  }
}

async function handleStep2() {
  const file = document.getElementById("idPictureInput").files[0];

  // No picture on file at all -> a file is required
  if (!verifyState.hasExistingPicture && !file) {
    $("#pictureError")
      .removeClass("d-none")
      .text("Please upload an ID picture.");
    return;
  }

  // Existing picture, user chose to overwrite -> a new file is required
  if (verifyState.hasExistingPicture && verifyState.overwrite && !file) {
    $("#pictureError")
      .removeClass("d-none")
      .text("Please choose a file to overwrite the existing picture.");
    return;
  }

  if (file && !["image/jpeg", "image/png"].includes(file.type)) {
    $("#pictureError")
      .removeClass("d-none")
      .text("Only JPEG, JPG, or PNG files are allowed.");
    return;
  }

  $("#verifyNextBtn").prop("disabled", true).text("Uploading...");
  try {
    if (file) {
      const formData = new FormData();
      formData.append("employee_id", verifyState.employeeId);
      formData.append("picture", file);
      formData.append("overwrite", verifyState.hasExistingPicture ? "1" : "0");

      const res = await fetch("functions/upload_employee_picture.php", {
        method: "POST",
        body: formData,
      });
      const result = await res.json();

      if (!result.success) {
        Swal.fire(
          "Upload Failed",
          result.message || "Could not upload picture.",
          "error",
        );
        return;
      }
    }

    goToStep(3);
    await finalizeVerification();
  } catch (err) {
    console.error("Picture upload failed:", err);
    Swal.fire(
      "Error",
      "Something went wrong while uploading the picture.",
      "error",
    );
  } finally {
    $("#verifyNextBtn").prop("disabled", false).text("Next");
  }
}

// ── STEP 3: Finalize — set status based on start_date ──────────
async function finalizeVerification() {
  $("#finalizeResult").html('<div class="spinner-border text-primary"></div>');
  try {
    const res = await fetch("functions/finalize_verification.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        employee_id: verifyState.employeeId,
        branch_code: verifyState.branchCode,
        loa_id: verifyState.loaId,
      }),
    });
    const result = await res.json();

    if (result.success) {
      $("#finalizeResult").html(`
        <i class="bi bi-check-circle-fill text-success" style="font-size:2.2rem;"></i>
        <p class="mt-2 mb-0">Verification complete. Employee status set to <strong>${result.status}</strong>.</p>
      `);
      if (
        typeof table !== "undefined" &&
        table &&
        typeof table.draw === "function"
      ) {
        table.draw(false); // refresh grid without resetting pagination
      }
    } else {
      $("#finalizeResult").html(`
        <i class="bi bi-x-circle-fill text-danger" style="font-size:2.2rem;"></i>
        <p class="mt-2 mb-0">${result.message || "Verification failed."}</p>
      `);
    }
  } catch (err) {
    console.error("Finalization failed:", err);
    $("#finalizeResult").html(
      `<p class="text-danger">Unable to complete verification.</p>`,
    );
  }
}
