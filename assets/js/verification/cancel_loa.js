// ─────────────────────────────────────────────────────────────
// cancel_loa.js
// Handles the "Cancel LOA" modal: admin/super_admin only (server
// re-checks role — see functions/cancel_loa.php). Requires the
// entered LOA code to match records before hard-deleting the
// letters_of_advice row.
//
// Uses its own .cancel-letter-box/.cancel-digit-box classes (not
// verify_loa.js's .loa-letter-box/.loa-digit-box) so the two
// modals' code-box input handlers never cross-fire, even though
// both modals are present on the page at the same time.
//
// IMPORTANT: wrapped in $(document).ready() for the same reason as
// verify_loa.js — modals/cancel_loa_modal.php is included in the
// page AFTER this <script> tag, so #cancelLoaConfirmBtn etc. don't
// exist yet at the moment this file first executes.
//
// Requires: jQuery, Bootstrap 5 bundle, SweetAlert2, and the
// DataTable instance `table` from verification.js (for row refresh).
// ─────────────────────────────────────────────────────────────

let cancelLoaState = {};

$(document).ready(function () {
  // Open trigger uses delegation since these buttons come from a
  // DataTable's ajax-rendered rows (added to the DOM after load).
  $(document).on("click", ".cancelLOABtn", function () {
    const btn = $(this);

    cancelLoaState = {
      employeeId: btn.data("employee-id"),
      loaId: btn.data("loa-id"),
      employeeName: btn.data("employee-name") || "",
    };

    // Reset modal UI
    $(".cancel-letter-box, .cancel-digit-box").val("");
    $("#cancelLoaCodeInput").val("");
    $("#cancelLoaCodeError").addClass("d-none").text("");
    $("#cancelLoaEmployeeName").text(cancelLoaState.employeeName);
    hideCancelLoading();

    new bootstrap.Modal(document.getElementById("cancelLOAModal")).show();
    $(".cancel-letter-box").first().trigger("focus");
  });

  // ── LOA code boxes: 4 letters, dash, 6 digits ─────────────────
  // Same behavior as verify_loa.js's boxes: filter char set, force
  // uppercase on letters, auto-advance focus, backspace-to-clear-
  // previous, and full-code paste distribution.

  function updateCancelLoaCodeValue() {
    const letters = $(".cancel-letter-box")
      .map(function () {
        return $(this).val();
      })
      .get()
      .join("");
    const digits = $(".cancel-digit-box")
      .map(function () {
        return $(this).val();
      })
      .get()
      .join("");
    $("#cancelLoaCodeInput").val(`${letters}-${digits}`);
  }

  function focusCancelBox(group, index) {
    $(`.cancel-${group}-box[data-index="${index}"]`)
      .trigger("focus")
      .select();
  }

  $(document).on("input", ".cancel-letter-box", function () {
    let val = $(this)
      .val()
      .replace(/[^a-zA-Z]/g, "")
      .toUpperCase()
      .slice(0, 1);
    $(this).val(val);
    updateCancelLoaCodeValue();

    if (val) {
      const index = parseInt($(this).data("index"), 10);
      if (index < 3) {
        focusCancelBox("letter", index + 1);
      } else {
        focusCancelBox("digit", 0);
      }
    }
  });

  $(document).on("input", ".cancel-digit-box", function () {
    let val = $(this)
      .val()
      .replace(/[^0-9]/g, "")
      .slice(0, 1);
    $(this).val(val);
    updateCancelLoaCodeValue();

    if (val) {
      const index = parseInt($(this).data("index"), 10);
      if (index < 5) focusCancelBox("digit", index + 1);
    }
  });

  $(document).on("keydown", ".cancel-letter-box, .cancel-digit-box", function (e) {
    if (e.key !== "Backspace" || $(this).val() !== "") return;

    const group = $(this).data("group");
    const index = parseInt($(this).data("index"), 10);

    if (group === "digit" && index > 0) {
      focusCancelBox("digit", index - 1);
      $(`.cancel-digit-box[data-index="${index - 1}"]`).val("");
    } else if (group === "digit" && index === 0) {
      focusCancelBox("letter", 3);
      $(`.cancel-letter-box[data-index="3"]`).val("");
    } else if (group === "letter" && index > 0) {
      focusCancelBox("letter", index - 1);
      $(`.cancel-letter-box[data-index="${index - 1}"]`).val("");
    }
    updateCancelLoaCodeValue();
  });

  $(document).on("paste", ".cancel-letter-box, .cancel-digit-box", function (e) {
    const pasted = (e.originalEvent.clipboardData || window.clipboardData)
      .getData("text")
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, "");

    if (!pasted) return;
    e.preventDefault();

    const letters = pasted.slice(0, 4).split("");
    const digits = pasted.slice(4, 10).split("");

    letters.forEach((ch, i) => {
      if (/[A-Z]/.test(ch)) $(`.cancel-letter-box[data-index="${i}"]`).val(ch);
    });
    digits.forEach((ch, i) => {
      if (/[0-9]/.test(ch)) $(`.cancel-digit-box[data-index="${i}"]`).val(ch);
    });

    updateCancelLoaCodeValue();

    if (letters.length < 4) {
      focusCancelBox("letter", letters.length);
    } else if (digits.length < 6) {
      focusCancelBox("digit", digits.length);
    } else {
      focusCancelBox("digit", 5);
    }
  });

  $("#cancelLoaConfirmBtn").on("click", handleCancelLoa);
});

// ── Loading lock ─────────────────────────────────────────────
// Same pattern as verify_loa.js: overlay + disable everything
// inside the modal while a request is in flight.
function showCancelLoading(text) {
  $("#cancelLoaLoadingText").text(text || "Processing...");
  $("#cancelLoaLoadingOverlay").removeClass("d-none");
  $("#cancelLOAModal").find("button, input").prop("disabled", true);
}

function hideCancelLoading() {
  $("#cancelLoaLoadingOverlay").addClass("d-none");
  $("#cancelLOAModal").find("button, input").prop("disabled", false);
}

// ── Confirm + delete ────────────────────────────────────────
async function handleCancelLoa() {
  const code = $("#cancelLoaCodeInput").val().trim();
  if (!/^[A-Z]{4}-\d{6}$/.test(code)) {
    $("#cancelLoaCodeError")
      .removeClass("d-none")
      .text("Please fill in all 10 characters of the LOA code.");
    return;
  }

  showCancelLoading("Verifying and deleting LOA...");
  try {
    const res = await fetch("functions/cancel_loa.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        employee_id: cancelLoaState.employeeId,
        loa_id: cancelLoaState.loaId,
        loa_code: code,
      }),
    });
    const result = await res.json();

    if (!result.success) {
      $("#cancelLoaCodeError")
        .removeClass("d-none")
        .text(result.message || "LOA code does not match. Cancellation aborted.");
      return;
    }

    bootstrap.Modal.getInstance(document.getElementById("cancelLOAModal")).hide();

    Swal.fire({
      icon: "success",
      title: "LOA Cancelled",
      text: "The LOA record has been permanently deleted.",
      confirmButtonColor: "#2d68c4",
    });

    if (
      typeof table !== "undefined" &&
      table &&
      typeof table.draw === "function"
    ) {
      table.draw(false); // refresh grid without resetting pagination
    }
  } catch (err) {
    console.error("LOA cancellation failed:", err);
    $("#cancelLoaCodeError")
      .removeClass("d-none")
      .text("Something went wrong. Please try again.");
  } finally {
    hideCancelLoading();
  }
}