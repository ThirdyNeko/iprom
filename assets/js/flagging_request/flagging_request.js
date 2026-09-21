$(function () {
  // ---------------------------------------------------------------
  // DataTable init — server-side processing, matching the
  // get_flagging_requests SP (ROW_NUMBER paging, COUNT(*) OVER total).
  // Column order below MUST match fetch_flagging_requests.php's
  // $sortColumns mapping.
  // ---------------------------------------------------------------
  const columns = [
    {
      data: "full_name",
      render: (d, type, r) => {
        if (type !== "display") return d;
        const isChecked = Number(r.is_checked) === 1;
        return !isChecked
          ? `<span class="text-danger fw-bold">●</span> ${d}`
          : d;
      },
    },
    { data: "branch" },
    { data: "brand" },
    { data: "employment_status" },
    { data: "sub_status" },
    { data: "status", render: statusBadge },
    { data: "requested_by" },
    {
      data: "requested_date",
      render: (d) => (d ? new Date(d).toLocaleString() : "—"),
    },
  ];

  // Unflagging is the only action left on a request — no more
  // approve/reject/cancel. Who can unflag a given row:
  //   - the requester themself (can always unflag their own flag)
  //   - audit_manager can unflag any request submitted by an audit role
  //     (audit_manager or audit_supervisor)
  //   - admin/super_admin can unflag requests submitted by a branch_manager
  // Client-side check is for showing/hiding the button only —
  // unflag_request.php / the SQL proc re-verify this independently
  // before actually unflagging.
  function canUnflagRow(r) {
    const role = (CURRENT_USER_ROLE || "").toLowerCase();
    const requesterRole = (r.requester_role || "").toLowerCase();

    const isOwner = r.requested_by === CURRENT_USER_NAME;
    const isAuditManagerOverAudit =
      role === "audit_manager" &&
      (requesterRole === "audit_manager" ||
        requesterRole === "audit_supervisor");
    const isAdminOverBranchManager =
      (role === "admin" || role === "super_admin") &&
      requesterRole === "branch_manager";

    return isOwner || isAuditManagerOverAudit || isAdminOverBranchManager;
  }

  // Attachments are visible to: the requester themself, anyone with the
  // literal 'admin' or 'super_admin' role, and — as an explicit
  // exception — an audit_manager viewing a request submitted by an
  // audit_supervisor. Client-side check is for showing/hiding the
  // button only — fetch_flagging_attachments.php independently
  // re-verifies this before returning any image data.
  function canViewAttachments(r) {
    const role = (CURRENT_USER_ROLE || "").toLowerCase();
    const isOwner = r.requested_by === CURRENT_USER_NAME;
    const isAdmin = role === "admin" || role === "super_admin";
    const isManagerOverSupervisor =
      role === "audit_manager" &&
      (r.requester_role || "").toLowerCase() === "audit_supervisor";
    return isOwner || isAdmin || isManagerOverSupervisor;
  }

  if (CAN_ACTION_FLAGGING_REQUESTS || CAN_REQUEST_FLAGGING) {
    columns.push({
      data: null,
      orderable: false,
      className: "fr-actions-col",
      render: (r) => {
        if (r.status !== "Flagged" || !canUnflagRow(r)) {
          return `<span class="text-muted">—</span>`;
        }

        return `
                    <button class="btn btn-outline-danger btn-sm fr-unflag-btn" data-id="${r.id}">
                        Unflag
                    </button>
                `;
      },
    });
  }

  const table = $("#FRtable").DataTable({
    serverSide: true,
    processing: true,
    searching: false, // custom search box below
    ajax: {
      url: "functions/fetch_flagging_requests.php",
      type: "GET",
      data: function (d) {
        d.status = $("#filterFRStatus").val(); // '' = All (SP treats empty/NULL as no filter)
      },
    },
    columns: columns,
    order: [[7, "desc"]],
  });

  function statusBadge(status) {
    const cls =
      status === "Unflagged"
        ? "status-badge-unflagged"
        : "status-badge-flagged";
    return `<span class="badge ${cls}">${status}</span>`;
  }

  // Custom search box -> DataTable's built-in search, which serverSide
  // mode forwards as search[value] on the next ajax request.
  let searchDebounce;
  $("#filterFRName").on("input", function () {
    clearTimeout(searchDebounce);
    const val = this.value;
    searchDebounce = setTimeout(() => table.search(val).draw(), 300);
  });

  // Status filter -> re-draw, which re-runs the ajax.data callback above
  // and sends the selected status on the next request.
  $("#filterFRStatus").on("change", function () {
    table.draw();
  });

  // ---------------------------------------------------------------
  // Unflag
  // ---------------------------------------------------------------
  $("#FRtable").on("click", ".fr-unflag-btn", function () {
    const id = $(this).data("id");

    Swal.fire({
      title: "To unflag this request, please provide a reason.",
      text: "This will clear the employee record status.",
      theme: "bootstrap-5",
      width: "600px",
      confirmButtonColor: "#0d6efd",
      html: `
        <textarea id="swal-unflag-remarks"
                  class="swal2-textarea"
                  maxlength="100"
                  placeholder="Reason for unflagging..."
                  style="background-color: #fffbdf; margin-top:0; margin-bottom:0; margin-left:0; margin-right:0; width:100%; height:100px; font-size:16px; resize:none;"></textarea>

        <div class="text-end text-muted" style="font-size:12px;">
          <span id="swal-unflag-remarks-count">0</span>/100
        </div>
      `,
      didOpen: () => {
        const titleEl = document.querySelector(".swal2-title");
        if (titleEl) {
          titleEl.style.fontSize = "20px";
          titleEl.style.marginTop = "15px";
          titleEl.style.marginLeft = "10px";
          titleEl.style.textAlign = "left";
        }
        const actions = document.querySelector(".swal2-actions");
        if (actions) {
          actions.style.width = "100%";
          actions.style.marginTop = "0";
          actions.style.paddingRight = "25px";
          actions.style.justifyContent = "flex-end";
        }
        const textarea = document.getElementById("swal-unflag-remarks");
        const counter = document.getElementById("swal-unflag-remarks-count");
        textarea.addEventListener("input", () => {
          counter.textContent = textarea.value.length;
        });
        textarea.focus();
      },
      preConfirm: () => {
        const value = document
          .getElementById("swal-unflag-remarks")
          .value.trim();
        if (!value) {
          Swal.showValidationMessage("A reason is required.");
          return false;
        }
        return value;
      },
      showCancelButton: true,
      confirmButtonText: "Submit",
    }).then((result) => {
      if (!result.isConfirmed) return;

      fetch("functions/unflag_request.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, remarks: result.value }),
      })
        .then((r) => r.json())
        .then((res) => {
          if (res.success) {
            Swal.fire("Successfully Unflagged", "", "success");
            table.ajax.reload(null, false);
          } else {
            Swal.fire("Error", res.message, "error");
          }
        })
        .catch(() => Swal.fire("Error", "Something went wrong.", "error"));
    });
  });

  // ---------------------------------------------------------------
  // Row click -> view request details modal
  // (skip clicks on the Actions cell/buttons — those have their own
  // handler above)
  // ---------------------------------------------------------------
  const viewModalEl = document.getElementById("viewFlaggingRequestModal");
  const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;

  $("#FRtable tbody").on("click", "tr", function (e) {
    if ($(e.target).closest(".fr-actions-col, .fr-unflag-btn").length) {
      return;
    }

    const rowData = table.row(this).data();
    if (!rowData) return;

    populateViewModal(rowData);
    viewModal.show();

    if (Number(rowData.is_checked) !== 1) {
      markFlaggingRequestChecked(rowData.id);
    }
  });

function markFlaggingRequestChecked(id) {
  fetch("functions/mark_flagging_checked.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id }),
  })
    .then((r) => r.json())
    .then((res) => {
      if (res.success && !res.skipped) {
        table.ajax.reload(null, false);
        refreshSidebarFlaggingBadge();
      }
    })
    .catch(() => {});
}

function refreshSidebarFlaggingBadge() {
  const badge = document.getElementById("sidebarFlaggingUncheckedBadge");
  if (!badge) return; // not admin, badge doesn't exist on this session

  fetch("functions/get_flagging_request_count.php")
    .then((r) => r.json())
    .then((data) => {
      const count = parseInt(data.count, 10) || 0;
      if (count > 0) {
        badge.textContent = count > 99 ? "99+" : count;
        badge.classList.remove("d-none");
      } else {
        badge.classList.add("d-none");
      }
    })
    .catch(() => {});
}

  let currentViewRequestId = null;

  function populateViewModal(r) {
    $("#vfr_full_name").text(r.full_name || "—");
    $("#vfr_status_badge").html(statusBadge(r.status));
    $("#vfr_date_hired").text(
      r.date_hired ? new Date(r.date_hired).toLocaleDateString() : "—",
    );
    $("#vfr_branch").text(r.branch || "—");
    $("#vfr_brand").text(r.brand || "—");
    $("#vfr_employment_status").text(r.employment_status || "—");
    $("#vfr_sub_status").text(r.sub_status || "—");
    $("#vfr_requested_by").text(r.requested_by || "—");
    $("#vfr_requested_date").text(
      r.requested_date ? new Date(r.requested_date).toLocaleString() : "—",
    );
    $("#vfr_unflagged_by").text(r.unflagged_by || "—");
    $("#vfr_unflagged_date").text(
      r.unflagged_date ? new Date(r.unflagged_date).toLocaleString() : "—",
    );
    $("#vfr_unflag_remarks").text(
      r.unflag_remarks && r.unflag_remarks.trim() ? r.unflag_remarks : "—",
    );
    $("#vfr_remarks").text(
      r.remarks && r.remarks.trim() ? r.remarks : "No remarks provided.",
    );

    // Reset attachment section for this open
    currentViewRequestId = r.id;
    $("#vfr_attachments").empty();
    $("#vfr_attachments_wrapper").addClass("d-none");
    $("#vfr_view_attachments_btn")
      .toggleClass("d-none", !canViewAttachments(r))
      .html('<i class="bi bi-paperclip me-1"></i>View Attachments')
      .prop("disabled", false);
  }

  $("#vfr_view_attachments_btn").on("click", function () {
    const $btn = $(this);
    const $wrapper = $("#vfr_attachments_wrapper");

    // Toggle back closed if already loaded/open
    if (!$wrapper.hasClass("d-none")) {
      $wrapper.addClass("d-none");
      $btn.html('<i class="bi bi-paperclip me-1"></i>View Attachments');
      return;
    }

    $btn.prop("disabled", true).text("Loading...");
    loadViewAttachments(currentViewRequestId, () => {
      $wrapper.removeClass("d-none");
      $btn
        .prop("disabled", false)
        .html('<i class="bi bi-paperclip me-1"></i>Hide Attachments');
    });
  });

  function loadViewAttachments(requestId, onDone) {
    const $container = $("#vfr_attachments").empty().text("Loading...");

    fetch(
      "functions/fetch_flagging_attachments.php?request_id=" +
        encodeURIComponent(requestId),
    )
      .then((r) => r.json())
      .then((res) => {
        $container.empty();

        if (!res.success) {
          $container.append(
            `<span class="text-muted">${res.message || "Unable to load attachments."}</span>`,
          );
          return;
        }

        if (!res.attachments || !res.attachments.length) {
          $container.append('<span class="text-muted">No attachments.</span>');
          return;
        }

        res.attachments.forEach((att) => {
          $container.append(`
            <a href="${att.picture_data}" download="${att.filename || "attachment"}" target="_blank">
              <img src="${att.picture_data}" class="rounded border"
                   style="width:80px;height:80px;object-fit:cover;" alt="${att.filename || "attachment"}">
            </a>
          `);
        });
      })
      .catch(() => {
        $container
          .empty()
          .append(
            '<span class="text-muted">Failed to load attachments.</span>',
          );
      })
      .finally(() => {
        if (typeof onDone === "function") onDone();
      });
  }

  // ---------------------------------------------------------------
  // Request Flagging modal
  // ---------------------------------------------------------------
  const requestModalEl = document.getElementById("requestFlaggingModal");
  const requestModal = requestModalEl
    ? new bootstrap.Modal(requestModalEl)
    : null;
  const IS_BRANCH_MANAGER =
    (CURRENT_USER_ROLE || "").toLowerCase() === "branch_manager";
  let selectedEmployee = null;

  // ---------------------------------------------------------------
  // Attachments (max 3 images) for the Request Flagging form
  // ---------------------------------------------------------------
  const MAX_ATTACHMENTS = 3;
  const MAX_ATTACHMENT_MB = 5;
  let selectedAttachments = []; // array of File

  $("#fl_remarks").on("input", function () {
    $("#fl_remarks_count").text(this.value.length);
  });

  $("#fl_attachments_input").on("change", function () {
    const incoming = Array.from(this.files || []);
    this.value = ""; // allow re-selecting the same file after a remove

    for (const file of incoming) {
      if (selectedAttachments.length >= MAX_ATTACHMENTS) {
        Swal.fire(
          "Limit reached",
          `You can attach up to ${MAX_ATTACHMENTS} images.`,
          "warning",
        );
        break;
      }
      if (!/^image\/(png|jpe?g)$/.test(file.type)) {
        Swal.fire(
          "Unsupported file",
          `"${file.name}" isn't a supported image type.`,
          "warning",
        );
        continue;
      }
      if (file.size > MAX_ATTACHMENT_MB * 1024 * 1024) {
        Swal.fire(
          "File too large",
          `"${file.name}" exceeds ${MAX_ATTACHMENT_MB}MB.`,
          "warning",
        );
        continue;
      }
      selectedAttachments.push(file);
    }

    renderAttachmentPreviews();
  });

  function renderAttachmentPreviews() {
    const $preview = $("#fl_attachments_preview").empty();

    selectedAttachments.forEach((file, idx) => {
      const url = URL.createObjectURL(file);
      const $thumb = $(`
        <div class="position-relative" style="width:80px;">
          <img src="${url}" class="rounded border" style="width:80px;height:80px;object-fit:cover;">
          <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0
                      fl-remove-attachment" data-idx="${idx}"
                  style="width:20px;height:20px;line-height:1;">&times;</button>
        </div>
      `);
      $preview.append($thumb);
    });

    $("#fl_attachments_input").prop(
      "disabled",
      selectedAttachments.length >= MAX_ATTACHMENTS,
    );
  }

  $("#fl_attachments_preview").on(
    "click",
    ".fl-remove-attachment",
    function () {
      const idx = $(this).data("idx");
      selectedAttachments.splice(idx, 1);
      renderAttachmentPreviews();
    },
  );

  $("#openRequestFlaggingBtn").on("click", function () {
    resetRequestForm();
    populateBranchDropdown();
    requestModal.show();
  });

  function resetRequestForm() {
    selectedEmployee = null;
    allBranchEmployees = [];
    selectedAttachments = [];
    $("#fl_attachments_preview").empty();
    $("#fl_attachments_input").val("").prop("disabled", false);
    $("#fl_branch_select").empty();
    $("#fl_brand_select")
      .empty()
      .append('<option value="">Select a branch first...</option>')
      .prop("disabled", true);
    $("#fl_employee_select")
      .empty()
      .append('<option value="">Select a brand first...</option>')
      .prop("disabled", true);
    [
      "first_name",
      "middle_name",
      "last_name",
      "suffix",
      "date_hired",
      "gender",
      "marital_status",
      "employment_status",
      "sub_status",
    ].forEach((f) => $("#fl_" + f).val(""));
    $("#fl_employee_id").val("");
    $("#fl_remarks").val("");
    $("#fl_remarks_count").text("0");
    $("#submitFlaggingRequestBtn").prop("disabled", true);
  }

  // Branch dropdown:
  // - branch_manager: locked to their single session branch, cannot change.
  //   The server independently re-validates this too — never trust the
  //   disabled select alone.
  // - audit_manager / audit_supervisor: unrestricted — full branch list
  //   fetched from the server, not limited to their own session branch.
  //
  // Dropdown displays the branch NAME but the value (and everything sent
  // to the server) is the branch_code — matching how employee_info.branch /
  // flagging_request.branch actually store codes.
  //
  // Selection order is Branch -> Brand -> Promodiser: picking a branch
  // loads that branch's employees once (cached in allBranchEmployees) and
  // derives the Brand dropdown from their distinct brand values; picking a
  // brand then filters that cache into the Promodiser dropdown.
  let allBranchEmployees = [];

  function populateBranchDropdown() {
    const $select = $("#fl_branch_select").empty();

    if (IS_BRANCH_MANAGER) {
      const codes = (CURRENT_USER_BRANCH || "")
        .split(",")
        .map((b) => b.trim())
        .filter(Boolean);

      if (!codes.length) {
        $select
          .append('<option value="">No branch assigned</option>')
          .prop("disabled", true);
        return;
      }

      const myCode = codes[0];
      $select
        .append(`<option value="${myCode}">Loading...</option>`)
        .prop("disabled", true);

      // Resolve the display name for their one locked branch
      fetch("functions/fetch_all_branches.php")
        .then((r) => r.json())
        .then((branches) => {
          const match = (branches || []).find(
            (b) => String(b.branch_code) === String(myCode),
          );
          const label = match ? match.branch : myCode;
          $select.empty().append(`<option value="${myCode}">${label}</option>`);
          $select.prop("disabled", true);
          loadBrandsForBranch(myCode);
        })
        .catch(() => {
          $select
            .empty()
            .append(`<option value="${myCode}">${myCode}</option>`);
          $select.prop("disabled", true);
          loadBrandsForBranch(myCode);
        });
      return;
    }

    // audit_manager / audit_supervisor: full branch list, unrestricted
    $select
      .append('<option value="">Loading branches...</option>')
      .prop("disabled", true);

    fetch("functions/fetch_all_branches.php")
      .then((r) => r.json())
      .then((branches) => {
        $select.empty();

        if (branches.error || !branches.length) {
          $select
            .append('<option value="">No branches available</option>')
            .prop("disabled", true);
          return;
        }

        $select.append('<option value="">Select branch...</option>');
        branches.forEach((b) =>
          $select.append(
            `<option value="${b.branch_code}">${b.branch}</option>`,
          ),
        );
        $select.prop("disabled", false);
      })
      .catch(() => {
        $select
          .empty()
          .append('<option value="">Failed to load branches</option>')
          .prop("disabled", true);
      });
  }

  $("#fl_branch_select").on("change", function () {
    const branchCode = $(this).val();
    clearEmployeeFields();
    if (branchCode) {
      loadBrandsForBranch(branchCode);
    } else {
      allBranchEmployees = [];
      $("#fl_brand_select")
        .empty()
        .append('<option value="">Select a branch first...</option>')
        .prop("disabled", true);
      $("#fl_employee_select")
        .empty()
        .append('<option value="">Select a brand first...</option>')
        .prop("disabled", true);
    }
  });

  function loadBrandsForBranch(branchCode) {
    const $brandSelect = $("#fl_brand_select")
      .empty()
      .append('<option value="">Loading...</option>')
      .prop("disabled", true);
    $("#fl_employee_select")
      .empty()
      .append('<option value="">Select a brand first...</option>')
      .prop("disabled", true);

    fetch(
      "functions/fetch_branch_employees.php?branch=" +
        encodeURIComponent(branchCode),
    )
      .then((r) => r.json())
      .then((results) => {
        $brandSelect.empty();

        if (results.error) {
          allBranchEmployees = [];
          $brandSelect.append(`<option value="">${results.error}</option>`);
          return;
        }
        if (!results.length) {
          allBranchEmployees = [];
          $brandSelect.append(
            '<option value="">No employees found for this branch</option>',
          );
          return;
        }

        allBranchEmployees = results;

        const brands = [
          ...new Set(results.map((e) => e.brand).filter(Boolean)),
        ].sort();

        if (!brands.length) {
          $brandSelect.append(
            '<option value="">No brands found for this branch</option>',
          );
          return;
        }

        $brandSelect.append('<option value="">Select brand...</option>');
        brands.forEach((brand) => {
          $brandSelect.append(`<option value="${brand}">${brand}</option>`);
        });
        $brandSelect.prop("disabled", false);
      })
      .catch(() => {
        allBranchEmployees = [];
        $brandSelect
          .empty()
          .append('<option value="">Failed to load brands</option>');
      });
  }

  $("#fl_brand_select").on("change", function () {
    const brand = $(this).val();
    clearEmployeeFields();
    if (brand) {
      populateEmployeesForBrand(brand);
    } else {
      $("#fl_employee_select")
        .empty()
        .append('<option value="">Select a brand first...</option>')
        .prop("disabled", true);
    }
  });

  function populateEmployeesForBrand(brand) {
    const $empSelect = $("#fl_employee_select").empty();
    const filtered = allBranchEmployees.filter((e) => e.brand === brand);

    if (!filtered.length) {
      $empSelect
        .append('<option value="">No promodisers found for this brand</option>')
        .prop("disabled", true);
      return;
    }

    $empSelect.append('<option value="">Select promodiser...</option>');
    filtered.forEach((emp) => {
      $empSelect.append(
        `<option value="${emp.employee_id}">${emp.first_name} ${emp.last_name}</option>`,
      );
    });
    $empSelect.prop("disabled", false);
    $empSelect.data("employees", filtered);
  }

  $("#fl_employee_select").on("change", function () {
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

  function clearEmployeeFields() {
    selectedEmployee = null;
    [
      "first_name",
      "middle_name",
      "last_name",
      "suffix",
      "date_hired",
      "gender",
      "marital_status",
      "employment_status",
      "sub_status",
    ].forEach((f) => $("#fl_" + f).val(""));
    $("#fl_employee_id").val("");
    $("#submitFlaggingRequestBtn").prop("disabled", true);
  }

  function selectEmployee(emp) {
    selectedEmployee = emp;

    $("#fl_employee_id").val(emp.employee_id);
    $("#fl_first_name").val(emp.first_name);
    $("#fl_middle_name").val(emp.middle_name);
    $("#fl_last_name").val(emp.last_name);
    $("#fl_suffix").val(emp.suffix);
    $("#fl_date_hired").val(
      emp.date_hired
        ? (() => {
            const d = new Date(emp.date_hired);
            return d.getMonth() + 1 + "/" + d.getDate() + "/" + d.getFullYear();
          })()
        : "",
    );
    $("#fl_gender").val(emp.gender);
    $("#fl_marital_status").val(emp.marital_status);
    $("#fl_employment_status").val(emp.employment_status);
    $("#fl_sub_status").val(emp.sub_status);

    $("#submitFlaggingRequestBtn").prop("disabled", false);
  }

  $("#submitFlaggingRequestBtn").on("click", function () {
    if (!selectedEmployee) return;

    if (!$("#fl_remarks").val().trim()) {
      Swal.fire(
        "Remarks required",
        "Please provide a reason for this flagging request.",
        "warning",
      );
      return;
    }

    const $btn = $(this);
    if ($btn.prop("disabled")) return; // already submitting — ignore extra clicks
    $btn.prop("disabled", true);

    const formData = new FormData();
    formData.append("employee_id", $("#fl_employee_id").val());
    formData.append("first_name", $("#fl_first_name").val());
    formData.append("middle_name", $("#fl_middle_name").val());
    formData.append("last_name", $("#fl_last_name").val());
    formData.append("suffix", $("#fl_suffix").val());
    formData.append("date_hired", $("#fl_date_hired").val());
    formData.append("gender", $("#fl_gender").val());
    formData.append("marital_status", $("#fl_marital_status").val());
    formData.append("branch", $("#fl_branch_select").val()); // picked directly — already the branch_code
    formData.append("brand", $("#fl_brand_select").val()); // picked directly
    formData.append("employment_status", $("#fl_employment_status").val());
    formData.append("sub_status", $("#fl_sub_status").val());
    formData.append("remarks", $("#fl_remarks").val());

    selectedAttachments.forEach((file) =>
      formData.append("attachments[]", file),
    );

    fetch("functions/submit_flagging_request.php", {
      method: "POST",
      body: formData, // no Content-Type header — browser sets the multipart boundary
    })
      .then((r) => r.json())
      .then((res) => {
        if (res.success) {
          Swal.fire({ title: "Flagging request submitted", icon: "success" });
          requestModal.hide();
          table.ajax.reload(null, false);
        } else {
          Swal.fire("Error", res.message, "error");
          $btn.prop("disabled", false); // let them retry on genuine failure
        }
      })
      .catch(() => {
        Swal.fire("Error", "Something went wrong.", "error");
        $btn.prop("disabled", false);
      });
  });
});
