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
      { data: "promodiser" },
      { data: "agency" },
      { data: "employment_status" },
      { data: "sub_status" },
      { data: "effectivity_date_display" },
      {
        data: null,
        width: "180px",
        className: "text-center px-1",
        orderable: false,
        render: function (data) {
          const canVerify =
            typeof CURRENT_USER_ROLE === "string" &&
            CURRENT_USER_ROLE.toLowerCase() === "branch_manager";

          const verifyBtnHtml = canVerify
            ? `<button class="btn btn-success btn-sm px-2 py-1 verifyLOABtn"
                data-loa-id="${data.loa_id}"
                data-employee-id="${data.employee_id ?? ""}"
                data-branch="${data.branch_code ?? ""}">
                <i class="bi bi-patch-check me-1"></i>Verify
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
                data-remarks="${data.remarks ?? ""}">
                <i class="bi bi-printer me-1"></i>View LOA
              </button>

              ${verifyBtnHtml}
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
  // so the verification modal logic stays in its own file.
});