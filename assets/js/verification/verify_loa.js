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
let cropper = null; // Cropper.js instance for the currently selected file (Step 2)
const CROP_OUTPUT_WIDTH = 350;
const CROP_OUTPUT_HEIGHT = 350; // 1.5" x 1.5" ID photo -- square output

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
    $(".loa-code-box").val("");
    $("#loaCodeInput").val("");
    $("#loaCodeError").addClass("d-none").text("");
    $("#idPictureInput").val("");
    $("#pictureError").addClass("d-none").text("");
    $("#existingPictureWrap").addClass("d-none");
    $("#uploadWrap").removeClass("d-none");
    $("#uploadPrompt").text("Upload ID Picture");
    destroyCropper();
    hideLoading();

    // Fresh open -> X visible, Okay hidden
    $("#verifyCloseXBtn").removeClass("d-none").prop("disabled", false);
    $("#verifyOkayBtn").addClass("d-none");

    goToStep(1, { animate: false });
    new bootstrap.Modal(document.getElementById("verifyLOAModal")).show();
    $(".loa-code-box").first().trigger("focus");
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

  $("#cropZoomInBtn").on("click", function () {
    if (cropper) cropper.zoom(0.1);
  });

  $("#cropZoomOutBtn").on("click", function () {
    if (cropper) cropper.zoom(-0.1);
  });

  $("#cropResetBtn").on("click", function () {
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
function goToStep(step, opts) {
  const animate = !opts || opts.animate !== false;
  const current = $(".verify-step").not(".d-none");
  const next = $(`#verifyStep${step}`);

  currentStep = step;

  $(".step-item").removeClass("active");
  $(`.step-item[data-step="${step}"]`).addClass("active");
  $("#verifyBackBtn").toggleClass("d-none", step === 1 || step === 3);
  $("#verifyNextBtn").toggleClass("d-none", step === 3);

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
    hideLoading();
  }
}

// ── STEP 3: Finalize — set status based on start_date ──────────
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
    // Whatever the outcome, the only way to close now is Okay.
    $("#verifyOkayBtn").removeClass("d-none").prop("disabled", false);
  }
}
