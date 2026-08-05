let bulkVerifyMode = false;
// Keyed by loa_id (string) -> { employeeId, branchCode }. finalize_verification's
// SP needs employee_id + branch_code alongside loa_id, so a Set of bare ids
// isn't enough -- carry the row's data through instead of re-fetching it.
let bulkVerifySelected = new Map();

function updateBulkVerifyBar() {
  $("#bulkVerifySelectedCount").text(`${bulkVerifySelected.size} selected`);
  $("#bulkVerifyConfirmBtn").prop("disabled", bulkVerifySelected.size === 0);
}

$(document).ready(function () {
  table = $("#LOAtable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    ordering: false,

    ajax: {
      url: "functions/fetch_loa.php",
      type: "POST",
      data: function (d) {
        d.name = $("#filterName").val();
        // Sent so fetch_loa.php can restrict results to the caller's branch(es)
        // for role = branch manager / staff. The stored procedure/query should
        // treat this as the source of truth (from $_SESSION), not trust a
        // client-supplied branch override.
        d.role = CURRENT_USER_ROLE;
        d.branch = CURRENT_USER_BRANCH;
      },
    },

    columns: [
      // ── Bulk verify checkbox column ──────────────────────────────
      // Hidden by default (toggled via #toggleBulkVerifyBtn). Only
      // rendered for admin / super_admin — mirrors the same role gate
      // used for the per-row Verify button below.
      {
        data: null,
        orderable: false,
        visible: false, // controlled purely via table.column(0).visible() -- don't also fight it with a CSS d-none class
        className: "text-center bulk-verify-col",
        render: function (data) {
          const role =
            typeof CURRENT_USER_ROLE === "string"
              ? CURRENT_USER_ROLE.toLowerCase()
              : "";
          const canBulkVerify = role === "admin" || role === "super_admin";
          if (!canBulkVerify) return "";

          const checked = bulkVerifySelected.has(String(data.loa_id))
            ? "checked"
            : "";
          return `<input type="checkbox" class="form-check-input bulkVerifyCheckbox"
                    data-loa-id="${data.loa_id}"
                    data-employee-id="${data.employee_id ?? ""}"
                    data-branch="${data.branch_code ?? ""}"
                    data-effectivity-date="${data.effectivity_date ?? ""}"
                    ${checked}>`;
        },
      },
      { data: "promodiser" },
      { data: "agency" },
      { data: "employment_status" },
      { data: "sub_status" },
      { data: "effectivity_date_display" },
      {
        data: null,
        width: "220px",
        className: "text-center px-1",
        orderable: false,
        render: function (data) {
          const role =
            typeof CURRENT_USER_ROLE === "string"
              ? CURRENT_USER_ROLE.toLowerCase()
              : "";

          const canVerify = role === "branch_manager";
          // Cancel LOA (hard delete, confirmed via LOA code entry) is
          // restricted to admin / super_admin. This is UI convenience
          // only -- functions/cancel_loa.php independently re-checks
          // $_SESSION['role'] server-side before deleting anything.
          const canCancel = role === "admin" || role === "super_admin";

          const verifyBtnHtml = canVerify
            ? `<button class="btn btn-success btn-sm px-2 py-1 verifyLOABtn"
                data-loa-id="${data.loa_id}"
                data-employee-id="${data.employee_id ?? ""}"
                data-branch="${data.branch_code ?? ""}"
                data-biometric-number="${data.biometric_number ?? ""}">
                <i class="bi bi-patch-check me-1"></i>Verify
              </button>`
            : "";

          const cancelBtnHtml = canCancel
            ? `<button class="btn btn-outline-danger btn-sm px-2 py-1 cancelLOABtn"
                data-loa-id="${data.loa_id}"
                data-employee-id="${data.employee_id ?? ""}"
                data-employee-name="${data.promodiser ?? ""}"
                data-employee-branch="${data.branch_name ?? data.branch_code ?? ""}">
                <i class="bi bi-x-circle me-1"></i>Cancel
              </button>`
            : "";

          return `
            <div class="action-btns d-flex justify-content-center gap-1">
              <button class="btn btn-primary btn-sm px-2 py-1 printLOABtn"
                data-loa-id="${data.loa_id}"
                data-employee-id="${data.employee_id ?? ""}"
                data-recipient-name="${data.recipient_name ?? ""}"
                data-recipient-position="${data.recipient_position ?? ""}"
                data-first-name="${data.first_name ?? ""}"
                data-middle-name="${data.middle_name ?? ""}"
                data-last-name="${data.last_name ?? ""}"
                data-suffix="${data.suffix ?? ""}"
                data-biometric-number="${data.biometric_number ?? ""}"
                data-branch="${data.branch_code ?? ""}"
                data-roving-branches='${JSON.stringify(data.roving_branches ?? [])}'
                data-brand="${data.brand ?? ""}"
                data-multi-brands='${JSON.stringify(data.multi_brands ?? [])}'
                data-agency="${data.agency ?? ""}"
                data-employment-status="${data.employment_status ?? ""}"
                data-sub-status="${data.sub_status ?? ""}"
                data-status="${data.status ?? ""}"
                data-effectivity-date="${data.effectivity_date ?? ""}"
                data-end-date="${data.end_date ?? ""}"
                data-remarks="${data.remarks ?? ""}"
                data-issued-by="${data.issued_by ?? ""}"
                data-issued-position="${data.issued_position ?? ""}"
                data-updated-at="${data.last_updated ?? ""}">
                <i class="bi bi-printer me-1"></i>View LOA
              </button>

              ${verifyBtnHtml}
              ${cancelBtnHtml}
            </div>
          `;
        },
      },
    ],
  });

  $("#filterName").on("input", function () {
    table.draw();
  });

  // ── Print LOA click handler ──────────────────────────────────────
  $("#LOAtable").on("click", ".printLOABtn", async function () {
    const btn = $(this);

    // Disable button while generating
    btn
      .prop("disabled", true)
      .html('<i class="bi bi-hourglass-split me-1"></i>Generating...');

    // 🔥 FIX: generate_letter_pdf.php now reads the business ID from
    // `employee_id` (not `id`), to stay consistent with pdf.js's basePayload.
    const payload = {
      employee_id: btn.data("employee-id"), // employee_info.employee_id (business ID, e.g. "EMP-...")
      loa_id: btn.data("loa-id"),
      recipient_name: btn.data("recipient-name"),
      recipient_position: btn.data("recipient-position"),
      first_name: btn.data("first-name"),
      middle_name: btn.data("middle-name"),
      last_name: btn.data("last-name"),
      suffix: btn.data("suffix"),
      biometric_number: btn.data("biometric-number"),
      branch: btn.data("branch"),
      roving_branches: btn.data("roving-branches"), // already parsed array by jQuery
      brand: btn.data("brand"),
      multi_brands: btn.data("multi-brands"), // already parsed array by jQuery
      agency: btn.data("agency"),
      employment_status: btn.data("employment-status"),
      sub_status: btn.data("sub-status"),
      status: btn.data("status"),
      effectivity_date: btn.data("effectivity-date"),
      end_date: btn.data("end-date"),
      remarks: btn.data("remarks"),
      // Reprinting an already-issued LOA — use the ORIGINAL issuer stored
      // on the record (from the DB), not the current viewer's session.
      issued_by: btn.data("issued-by"),
      issued_position: btn.data("issued-position"),
      // "Last updated" timestamp for the PDF footer — sourced from the DB
      // row (see fetch_loa.php). Empty on a brand-new LOA generated via
      // pdf.js, which never populates this field; generate_letter_pdf.php
      // falls back to the current time in that case.
      updated_at: btn.data("updated-at"),
    };

    try {
      const response = await fetch("functions/generate_letter_pdf.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      if (!response.ok) throw new Error(`Server error: ${response.status}`);

      const blob = await response.blob();
      const url = URL.createObjectURL(blob);

      // Open PDF in new tab; browser handles print/view
      window.open(url, "_blank");

      // Clean up the object URL after the tab has had time to load it
      setTimeout(() => URL.revokeObjectURL(url), 10000);
    } catch (err) {
      console.error("LOA generation failed:", err);
      Swal.fire(
        "Error",
        "Failed to generate Letter of Advice. Please try again.",
        "error",
      );
    } finally {
      btn
        .prop("disabled", false)
        .html('<i class="bi bi-printer me-1"></i>View LOA');
    }
  });

  // Note: the "Verify" button click handler lives in assets/js/verify_loa.js
  // so the verification modal logic stays in its own file. Likewise, the
  // "Cancel" button click handler lives in assets/js/verification/cancel_loa.js.

  // ── Bulk verify: toggle mode on/off ───────────────────────────────
  $("#toggleBulkVerifyBtn").on("click", function () {
    bulkVerifyMode = !bulkVerifyMode;
    bulkVerifySelected.clear();
    updateBulkVerifyBar();

    $("#bulkVerifyBar")
      .toggleClass("d-none", !bulkVerifyMode)
      .toggleClass("d-flex", bulkVerifyMode);
    $(this)
      .toggleClass("btn-outline-primary", !bulkVerifyMode)
      .toggleClass("btn-primary", bulkVerifyMode);

    // visible() alone re-shows/hides the column's cells -- it's the only
    // mechanism controlling this column now (see the column def above).
    // Do NOT follow this with table.draw() -- this table is serverSide:true,
    // so draw() always fires a fresh ajax request to fetch_loa.php on every
    // single toggle, even though no data actually changed.
    table.column(0).visible(bulkVerifyMode);

    // Selections were just cleared above -- make sure the master checkbox
    // doesn't show a stale checked/indeterminate state from a previous
    // bulk-verify session. Runs AFTER visible() so this targets whatever
    // #bulkVerifySelectAll node exists post-toggle, not a stale reference.
    $("#bulkVerifySelectAll")
      .prop("checked", false)
      .prop("indeterminate", false);
  });

  $("#bulkVerifyCancelBtn").on("click", function () {
    bulkVerifyMode = false;
    bulkVerifySelected.clear();
    updateBulkVerifyBar();

    $("#bulkVerifyBar").removeClass("d-flex").addClass("d-none");
    $("#toggleBulkVerifyBtn")
      .removeClass("btn-primary")
      .addClass("btn-outline-primary");

    table.column(0).visible(false);
    $("#bulkVerifySelectAll")
      .prop("checked", false)
      .prop("indeterminate", false);
  });

  // Row checkbox toggling — selection is kept in bulkVerifySelected so it
  // survives pagination/redraw (DataTables destroys/rebuilds row DOM nodes).
  $("#LOAtable tbody").on("change", ".bulkVerifyCheckbox", function () {
    const loaId = String($(this).data("loa-id"));
    if (this.checked) {
      bulkVerifySelected.set(loaId, {
        employeeId: $(this).data("employee-id"),
        branchCode: $(this).data("branch"),
      });
    } else {
      bulkVerifySelected.delete(loaId);
    }
    updateBulkVerifyBar();
    syncSelectAllCheckboxState();
  });

  // Select all — applies to checkboxes currently rendered (i.e. current
  // page only, since this is server-side paging). Delegated (not a direct
  // $("#bulkVerifySelectAll").on(...) bind) because the column now starts
  // as visible:false, which means DataTables actually destroys and
  // recreates the <th> for this column each time it's toggled -- a direct
  // binding would attach to the original node and go dead the moment that
  // node gets replaced. Delegating from #LOAtable (which itself is never
  // replaced) re-resolves the selector on every event instead.
  $("#LOAtable").on("change", "#bulkVerifySelectAll", function () {
    const checked = this.checked;
    $("#LOAtable tbody .bulkVerifyCheckbox").each(function () {
      $(this).prop("checked", checked).trigger("change");
    });
  });

  // Keeps the master checkbox honest against what's actually selected on
  // the current page: checked only if every visible row is checked,
  // indeterminate if some (but not all) are, unchecked otherwise. Also
  // re-run on every DataTables redraw (page change, filter, etc.) since a
  // fresh set of rows -- with their own checked/unchecked state -- just
  // replaced the old ones.
  function syncSelectAllCheckboxState() {
    const $rowBoxes = $("#LOAtable tbody .bulkVerifyCheckbox");
    const total = $rowBoxes.length;
    const checkedCount = $rowBoxes.filter(":checked").length;

    $("#bulkVerifySelectAll")
      .prop("checked", total > 0 && checkedCount === total)
      .prop("indeterminate", checkedCount > 0 && checkedCount < total);
  }

  table.on("draw.dt", function () {
    if (bulkVerifyMode) syncSelectAllCheckboxState();
  });

  // ── Bulk verify: submit selected LOAs ─────────────────────────────
  $("#bulkVerifyConfirmBtn").on("click", async function () {
    if (bulkVerifySelected.size === 0) return;

    const confirmResult = await Swal.fire({
      title: `Verify ${bulkVerifySelected.size} promodiser(s)?`,
      text: "This skips LOA code entry and ID picture upload. Employees will be set ACTIVE, or QUEUED if their effectivity date hasn't started yet.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, verify",
      confirmButtonColor: "#198754",
    });
    if (!confirmResult.isConfirmed) return;

    const btn = $(this);
    btn
      .prop("disabled", true)
      .html('<i class="bi bi-hourglass-split me-1"></i>Verifying...');

    try {
      const items = Array.from(bulkVerifySelected.entries()).map(
        ([loaId, info]) => ({
          loa_id: loaId,
          employee_id: info.employeeId,
          branch_code: info.branchCode,
        }),
      );

      const response = await fetch("functions/bulk_verify_loa.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items }),
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(result.message || `Server error: ${response.status}`);
      }

      Swal.fire(
        "Done",
        `${result.verified_count} verified successfully` +
          (result.failed_count ? `, ${result.failed_count} failed.` : "."),
        "success",
      );

      bulkVerifySelected.clear();
      updateBulkVerifyBar();
      table.draw(false);
    } catch (err) {
      console.error("Bulk verify failed:", err);
      Swal.fire(
        "Error",
        err.message || "Bulk verification failed. Please try again.",
        "error",
      );
    } finally {
      btn
        .prop("disabled", false)
        .html('<i class="bi bi-patch-check me-1"></i>Verify Selected');
    }
  });
});
