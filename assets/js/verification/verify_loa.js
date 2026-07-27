// ─────────────────────────────────────────────────────────────
// verify_loa.js
// Handles the "Verify LOA" modal: LOA code check -> ID picture
// check/upload -> confirm preview -> status finalization
// (ACTIVE / QUEUED).
//
// Flow is 4 steps:
//   1. LOA Code
//   2. ID Picture (existing/keep/overwrite or fresh upload + crop)
//   3. Confirm (shows the picture that will be submitted; user
//      must explicitly click "Confirm & Submit" -- Back returns
//      to Step 2 without finalizing anything)
//   4. Result (finalize_verification.php is only ever called once
//      Step 3 is confirmed)
//
// Picture storage note: pictures are stored as binary data in
// [IPROM_TEST].[dbo].[employee_pictures].[id_picture] (not on disk),
// so the backend returns/accepts base64 data URIs (`picture_data`)
// rather than file URLs.
//
// IMPORTANT: ALL bindings for elements inside the modal partial
// (modals/verify_loa_modal.php) use event delegation on
// `document`, NOT direct selectors like $("#verifyNextBtn").
// The modal HTML may not exist in the DOM yet at the moment this
// script runs (or even at $(document).ready() time, if it's loaded
// via a separate include/fetch that resolves later). A direct
// $("#id").on(...) binds to whatever matches AT THAT INSTANT --
// if the element isn't there yet, it binds to an empty set and
// silently does nothing, forever. Delegated binding on `document`
// re-resolves the selector every time the event bubbles up, so it
// works no matter when the modal markup actually lands.
//
// Requires: jQuery, Bootstrap 5 bundle, SweetAlert2, and the
// DataTable instance `table` from verification.js (for redraw).
// ─────────────────────────────────────────────────────────────

let verifyState = {};
let currentStep = 1;
let cropper = null; // Cropper.js instance for the currently selected file (Step 2)
const CROP_OUTPUT_WIDTH = 350;
const CROP_OUTPUT_HEIGHT = 350; // 1.5" x 1.5" ID photo -- square output

$(document).ready(function () {
  // Open trigger uses delegation since these buttons come from a
  // DataTable's ajax-rendered rows (added to the DOM after load).
  $(document).on("click", ".verifyLOABtn", function () {
    const btn = $(this);

    revokeConfirmPreviewUrl();

    verifyState = {
      employeeId: btn.data("employee-id"),
      loaId: btn.data("loa-id"),
      branchCode: btn.data("branch"),
      hasExistingPicture: false,
      existingPictureData: null,
      overwrite: false,
      pendingPreviewUrl: null, // objectURL for a freshly cropped picture, revoked on close/reopen
      pendingSource: null, // "existing" | "new" -- what's about to be submitted on Confirm
    };

    // Reset modal UI
    $(".loa-code-box").val("");
    $("#loaCodeInput").val("");
    $("#loaCodeError").addClass("d-none").text("");
    $("#idPictureInput").val("");
    $("#pictureError").addClass("d-none").text("");
    $("#existingPictureWrap").addClass("d-none");
    $("#uploadWrap").removeClass("d-none");
    $("#uploadPrompt").text("Upload ID Picture");
    $("#verifyConfirmPicture").attr("src", "");
    $("#verifyConfirmSourceLabel").text("");
    $("#finalizeResult").empty();
    destroyCropper();
    hideLoading();

    // Fresh open -> X visible, Okay hidden
    $("#verifyCloseXBtn").removeClass("d-none").prop("disabled", false);
    $("#verifyOkayBtn").addClass("d-none");

    goToStep(1, { animate: false });
    new bootstrap.Modal(document.getElementById("verifyLOAModal")).show();
    $(".loa-code-box").first().trigger("focus");
  });

  // Clean up any pending object URL once the modal is fully hidden,
  // so re-opening it (or opening it for a different row) never leaks
  // memory or shows a stale picture. This one stays delegated too --
  // the modal element itself might not exist at ready() time, so we
  // can't call $("#verifyLOAModal").on(...) directly either.
  $(document).on("hidden.bs.modal", "#verifyLOAModal", function () {
    revokeConfirmPreviewUrl();
  });

  // ── LOA code boxes: 4 letters, dash, 6 digits ─────────────────
  // Each box holds exactly one character. Typing in one box filters
  // the character (letters-only / digits-only), forces uppercase for
  // letters, and auto-advances focus to the next box. Backspace on an
  // empty box moves focus back and clears the previous box. Pasting a
  // full code into any box distributes it across all the boxes.
  // updateLoaCodeValue() keeps the hidden #loaCodeInput in sync as
  // "ABCD-123456", which is what handleStep1() actually submits.

  function updateLoaCodeValue() {
    const letters = $(".loa-letter-box")
      .map(function () {
        return $(this).val();
      })
      .get()
      .join("");
    const digits = $(".loa-digit-box")
      .map(function () {
        return $(this).val();
      })
      .get()
      .join("");
    $("#loaCodeInput").val(`${letters}-${digits}`);
  }

  function focusBox(group, index) {
    $(`.loa-code-box[data-group="${group}"][data-index="${index}"]`)
      .trigger("focus")
      .select();
  }

  $(document).on("input", ".loa-letter-box", function () {
    let val = $(this)
      .val()
      .replace(/[^a-zA-Z]/g, "")
      .toUpperCase()
      .slice(0, 1);
    $(this).val(val);
    updateLoaCodeValue();

    if (val) {
      const index = parseInt($(this).data("index"), 10);
      if (index < 3) {
        focusBox("letter", index + 1);
      } else {
        focusBox("digit", 0);
      }
    }
  });

  $(document).on("input", ".loa-digit-box", function () {
    let val = $(this)
      .val()
      .replace(/[^0-9]/g, "")
      .slice(0, 1);
    $(this).val(val);
    updateLoaCodeValue();

    if (val) {
      const index = parseInt($(this).data("index"), 10);
      if (index < 5) focusBox("digit", index + 1);
    }
  });

  $(document).on("keydown", ".loa-code-box", function (e) {
    if (e.key !== "Backspace" || $(this).val() !== "") return;

    const group = $(this).data("group");
    const index = parseInt($(this).data("index"), 10);

    if (group === "digit" && index > 0) {
      focusBox("digit", index - 1);
      $(`.loa-digit-box[data-index="${index - 1}"]`).val("");
    } else if (group === "digit" && index === 0) {
      focusBox("letter", 3);
      $(`.loa-letter-box[data-index="3"]`).val("");
    } else if (group === "letter" && index > 0) {
      focusBox("letter", index - 1);
      $(`.loa-letter-box[data-index="${index - 1}"]`).val("");
    }
    updateLoaCodeValue();
  });

  $(document).on("paste", ".loa-code-box", function (e) {
    const pasted = (e.originalEvent.clipboardData || window.clipboardData)
      .getData("text")
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, "");

    if (!pasted) return;
    e.preventDefault();

    const letters = pasted.slice(0, 4).split("");
    const digits = pasted.slice(4, 10).split("");

    letters.forEach((ch, i) => {
      if (/[A-Z]/.test(ch)) $(`.loa-letter-box[data-index="${i}"]`).val(ch);
    });
    digits.forEach((ch, i) => {
      if (/[0-9]/.test(ch)) $(`.loa-digit-box[data-index="${i}"]`).val(ch);
    });

    updateLoaCodeValue();

    if (letters.length < 4) {
      focusBox("letter", letters.length);
    } else if (digits.length < 6) {
      focusBox("digit", digits.length);
    } else {
      focusBox("digit", 5);
    }
  });

  // ── Footer nav ───────────────────────────────────────────────
  // Delegated: #verifyBackBtn / #verifyNextBtn live inside the
  // modal partial, which may not be in the DOM yet at ready() time.
  $(document).on("click", "#verifyBackBtn", function () {
    if (currentStep > 1) goToStep(currentStep - 1);
  });

  $(document).on("click", "#verifyNextBtn", function () {
    if (currentStep === 1) handleStep1();
    else if (currentStep === 2) handleStep2();
    else if (currentStep === 3) handleConfirmStep();
  });

  // "Keep Existing" no longer finalizes directly -- it just stages
  // the existing picture as what Confirm will submit.
  $(document).on("click", "#keepExistingBtn", function () {
    verifyState.overwrite = false;
    showConfirmStep({ source: "existing" });
  });

  $(document).on("click", "#overwriteBtn", function () {
    verifyState.overwrite = true;
    $("#uploadWrap").removeClass("d-none");
    $("#uploadPrompt").text(
      "Upload the new ID picture to overwrite the existing one.",
    );
  });

  $(document).on("change", "#idPictureInput", function () {
    $("#pictureError").addClass("d-none");
    const file = this.files[0];
    if (!file) {
      $("#cropWrap").addClass("d-none");
      destroyCropper();
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      $("#cropWrap").removeClass("d-none");
      $("#cropImage").attr("src", e.target.result);
      initCropper();
    };
    reader.readAsDataURL(file);
  });

  $(document).on("click", "#cropZoomInBtn", function () {
    if (cropper) cropper.zoom(0.1);
  });

  $(document).on("click", "#cropZoomOutBtn", function () {
    if (cropper) cropper.zoom(-0.1);
  });

  $(document).on("click", "#cropResetBtn", function () {
    if (cropper) cropper.reset();
  });
});

// ── Crop UI (Step 2) ────────────────────────────────────────────
// Cropper.js is initialized on #cropImage once a file is chosen.
// dragMode "move" + a locked aspect ratio means the crop frame stays
// fixed in place while the user drags/zooms the photo underneath it
// -- the standard "move around and crop" avatar-picker interaction.
function initCropper() {
  destroyCropper();

  const image = document.getElementById("cropImage");
  cropper = new Cropper(image, {
    aspectRatio: 1, // 1.5" x 1.5" -- matches the square photo guide frame
    viewMode: 1,
    dragMode: "move",
    autoCropArea: 1,
    cropBoxMovable: false,
    cropBoxResizable: false,
    toggleDragModeOnDblclick: false,
    guides: false,
    background: false,
  });
}

function destroyCropper() {
  if (cropper) {
    cropper.destroy();
    cropper = null;
  }
}

// Resolves with a cropped JPEG Blob, or null if there's no active
// cropper (i.e. no new file was chosen this session).
function getCroppedBlob() {
  return new Promise((resolve) => {
    if (!cropper) {
      resolve(null);
      return;
    }
    cropper
      .getCroppedCanvas({
        width: CROP_OUTPUT_WIDTH,
        height: CROP_OUTPUT_HEIGHT,
        imageSmoothingQuality: "high",
      })
      .toBlob((blob) => resolve(blob), "image/jpeg", 0.92);
  });
}

// ── Loading lock ─────────────────────────────────────────────
// Shows a full-body overlay + spinner over the modal and disables
// every interactive element inside it (buttons, inputs, close/X)
// so nothing can be clicked or typed while a request is in flight.
function showLoading(text) {
  $("#verifyLoadingText").text(text || "Processing...");
  $("#verifyLoadingOverlay").removeClass("d-none");
  $("#verifyLOAModal").find("button, input").prop("disabled", true);
}

function hideLoading() {
  $("#verifyLoadingOverlay").addClass("d-none");
  $("#verifyLOAModal").find("button, input").prop("disabled", false);
}

// ── Step navigation with a short fade transition ───────────────
// Steps: 1 LOA Code, 2 ID Picture, 3 Confirm, 4 Result.
function goToStep(step, opts) {
  const animate = !opts || opts.animate !== false;
  const current = $(".verify-step").not(".d-none");
  const next = $(`#verifyStep${step}`);

  currentStep = step;

  $(".step-item").removeClass("active");
  $(`.step-item[data-step="${step}"]`).addClass("active");

  $("#verifyBackBtn").toggleClass("d-none", step === 1 || step === 4);
  $("#verifyNextBtn").toggleClass("d-none", step === 4);
  $("#verifyNextBtn").text(step === 3 ? "Confirm & Submit" : "Next");

  if (animate && current.length && current.attr("id") !== next.attr("id")) {
    current.addClass("verify-step-fade-out");
    setTimeout(() => {
      current.addClass("d-none").removeClass("verify-step-fade-out");
      next.removeClass("d-none").addClass("verify-step-fade-in");
      setTimeout(() => next.removeClass("verify-step-fade-in"), 250);
    }, 180);
  } else {
    $(".verify-step").addClass("d-none");
    next.removeClass("d-none");
  }
}

// ── STEP 1: Verify LOA code against employee_info ──────────────
async function handleStep1() {
  const code = $("#loaCodeInput").val().trim();
  if (!/^[A-Z]{4}-\d{6}$/.test(code)) {
    $("#loaCodeError")
      .removeClass("d-none")
      .text("Please fill in all 10 characters of the LOA code.");
    return;
  }

  showLoading("Checking LOA code...");
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
    showLoading("Loading picture record...");
    await loadExistingPicture();
    goToStep(2);
  } catch (err) {
    console.error("LOA code verification failed:", err);
    Swal.fire("Error", "Unable to verify LOA code. Please try again.", "error");
  } finally {
    hideLoading();
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
      // Kept on verifyState so the Confirm step (Step 3) can preview it
      // without another round trip if the user picks "Keep Existing".
      verifyState.existingPictureData = result.picture_data;
      $("#existingPictureImg").attr("src", result.picture_data);
      $("#existingPictureWrap").removeClass("d-none");
      $("#uploadWrap").addClass("d-none");
    } else {
      verifyState.hasExistingPicture = false;
      verifyState.existingPictureData = null;
      $("#existingPictureWrap").addClass("d-none");
      $("#uploadWrap").removeClass("d-none");
      $("#uploadPrompt").text("No picture on file yet — please upload one.");
    }
  } catch (err) {
    console.error("Picture check failed:", err);
    // Fail safe: treat as no picture on file so the flow isn't blocked
    verifyState.hasExistingPicture = false;
    verifyState.existingPictureData = null;
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

  showLoading("Uploading picture...");
  try {
    if (file) {
      const croppedBlob = await getCroppedBlob();

      if (!croppedBlob) {
        Swal.fire(
          "Error",
          "Could not process the cropped image. Please try again.",
          "error",
        );
        return;
      }

      const formData = new FormData();
      formData.append("employee_id", verifyState.employeeId);
      formData.append("picture", croppedBlob, "id_picture.jpg");
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

      // Picture is uploaded and saved server-side at this point; go to
      // Confirm with the just-cropped blob as the preview source.
      showConfirmStep({ source: "new", blob: croppedBlob });
    } else {
      // No new file chosen and we weren't required to have one --
      // this only happens if hasExistingPicture is true and overwrite
      // was never toggled on (kept the existing picture implicitly).
      showConfirmStep({ source: "existing" });
    }
  } catch (err) {
    console.error("Picture upload failed:", err);
    Swal.fire(
      "Error",
      "Something went wrong while uploading the picture.",
      "error",
    );
  } finally {
    hideLoading();
  }
}

// ── STEP 3: Confirm — show the picture that will be submitted ──
// Nothing is finalized here. The user must click "Confirm & Submit"
// (the footer Next button, relabeled by goToStep) to proceed, or
// "Back" to return to Step 2 and pick/crop a different picture.
function showConfirmStep(opts) {
  revokeConfirmPreviewUrl();

  let previewSrc;
  if (opts.source === "new" && opts.blob) {
    previewSrc = URL.createObjectURL(opts.blob);
    verifyState.pendingPreviewUrl = previewSrc; // remembered so it can be revoked later
    $("#verifyConfirmSourceLabel").text("Newly uploaded picture");
  } else {
    previewSrc = verifyState.existingPictureData;
    $("#verifyConfirmSourceLabel").text(
      "Existing picture on file (kept as-is)",
    );
  }

  verifyState.pendingSource = opts.source;
  $("#verifyConfirmPicture").attr("src", previewSrc || "");

  goToStep(3);
}

function handleConfirmStep() {
  goToStep(4);
  finalizeVerification();
}

function revokeConfirmPreviewUrl() {
  if (verifyState && verifyState.pendingPreviewUrl) {
    URL.revokeObjectURL(verifyState.pendingPreviewUrl);
    verifyState.pendingPreviewUrl = null;
  }
}

// ── STEP 4: Finalize — set status based on start_date ───────────
async function finalizeVerification() {
  showLoading("Finalizing verification...");
  $("#finalizeResult").html('<div class="spinner-border text-primary"></div>');

  // While finalizing, no way out except waiting for the result --
  // hide the X entirely rather than just disabling it.
  $("#verifyCloseXBtn").addClass("d-none");

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
        <p class="mt-2 mb-0">Verification complete. Employee status set to <strong>QUEUED</strong>.</p>
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
  } finally {
    hideLoading();
    destroyCropper(); // no longer needed once we're on the result step
    revokeConfirmPreviewUrl(); // preview blob URL is no longer needed either
    // Whatever the outcome, the only way to close now is Okay.
    $("#verifyOkayBtn").removeClass("d-none").prop("disabled", false);
  }
}
