$(function () {
  // ---------------------------------------------------------------
  // DataTable init — server-side processing, matching the
  // get_blacklist_requests SP (ROW_NUMBER paging, COUNT(*) OVER total).
  // Column order below MUST match fetch_blacklist_request.php's
  // $sortColumns mapping.
  // ---------------------------------------------------------------
  const columns = [
    { data: "full_name" },
    { data: "branch" },
    { data: "brand" },
    { data: "employment_status" },
    {
      data: "end_date",
      render: (d) => (d ? new Date(d).toLocaleDateString() : "—"),
    },
    { data: "status", render: statusBadge },
    { data: "requested_by" },
    {
      data: "requested_date",
      render: (d) => (d ? new Date(d).toLocaleString() : "—"),
    },
  ];

  // Can the current user cancel THIS particular row? Client-side check is
  // for showing/hiding the button only — cancel_blacklist_request.php /
  // the SQL proc re-verify this independently before actually cancelling.
  function canCancelRow(r) {
    if (r.status !== "Pending") return false;
    const isOwner = r.requested_by === CURRENT_USER_NAME;
    const isManagerOverSupervisor =
      (CURRENT_USER_ROLE || "").toLowerCase() === "audit_manager" &&
      (r.requester_role || "").toLowerCase() === "audit_supervisor";
    return isOwner || isManagerOverSupervisor;
  }

  // Reject is only allowed on requests submitted by a branch_manager.
  // Client-side check is for showing/hiding the button only —
  // update_blacklist_request_status.php / the SQL proc must independently
  // re-verify requester_role === 'branch_manager' before actually rejecting.
  function canRejectRow(r) {
    return (r.requester_role || "").toLowerCase() === "branch_manager";
  }

  // Attachments are visible to: the requester themself, anyone with the
  // literal 'admin' or 'super_admin' role, and — as an explicit
  // exception — an audit_manager viewing a request submitted by an
  // audit_supervisor. Client-side check is for showing/hiding the
  // button only — fetch_blacklist_attachments.php independently
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

  if (CAN_ACTION_REQUESTS || CAN_REQUEST_BLACKLIST) {
    columns.push({
      data: null,
      orderable: false,
      className: "bl-actions-col",
      render: (r) => {
        if (r.status !== "Pending") {
          return `<span class="text-muted">—</span>`;
        }

        let buttons = "";

        if (CAN_ACTION_REQUESTS) {
          buttons += `
                    <button class="btn btn-success btn-sm bl-approve-btn" data-id="${r.id}">
                        <i class="bi bi-check-lg"></i>
                    </button>
                `;
          if (canRejectRow(r)) {
            buttons += `
                    <button class="btn btn-outline-danger btn-sm bl-reject-btn" data-id="${r.id}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                `;
          }
        }

        if (canCancelRow(r)) {
          buttons += `
                    <button class="btn btn-outline-secondary btn-sm bl-cancel-btn" data-id="${r.id}">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                `;
        }

        return buttons || `<span class="text-muted">—</span>`;
      },
    });
  }

  const table = $("#BLtable").DataTable({
    serverSide: true,
    processing: true,
    ajax: {
      url: "functions/fetch_blacklist_request.php",
      type: "GET",
      data: function (d) {
        d.status = $("#filterBLStatus").val(); // '' = All (SP treats empty/NULL as no filter)
      },
    },
    columns: columns,
    order: [[7, "desc"]],
  });

  function statusBadge(status) {
    const cls =
      status === "Approved"
        ? "status-badge-approved"
        : status === "Rejected"
          ? "status-badge-rejected"
          : status === "Cancelled"
            ? "status-badge-cancelled"
            : "status-badge-pending";
    return `<span class="badge ${cls}">${status}</span>`;
  }

  // Custom search box -> DataTable's built-in search, which serverSide
  // mode forwards as search[value] on the next ajax request.
  let searchDebounce;
  $("#filterBLName").on("input", function () {
    clearTimeout(searchDebounce);
    const val = this.value;
    searchDebounce = setTimeout(() => table.search(val).draw(), 300);
  });

  // Status filter -> re-draw, which re-runs the ajax.data callback above
  // and sends the selected status on the next request.
  $("#filterBLStatus").on("change", function () {
    table.draw();
  });

  // ---------------------------------------------------------------
  // Approve / Reject / Cancel
  // ---------------------------------------------------------------
  $("#BLtable").on("click", ".bl-approve-btn, .bl-reject-btn", function () {
    const id = $(this).data("id");
    const isApprove = $(this).hasClass("bl-approve-btn");
    const newStatus = isApprove ? "Approved" : "Rejected";

    Swal.fire({
      title: `${newStatus} this request?`,
      icon: isApprove ? "question" : "warning",
      showCancelButton: true,
      confirmButtonText: `Yes, ${newStatus.toLowerCase()}`,
    }).then((result) => {
      if (!result.isConfirmed) return;

      fetch("functions/update_blacklist_request_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, status: newStatus }),
      })
        .then((r) => r.json())
        .then((res) => {
          if (res.success) {
            Swal.fire("Done", res.message, "success");
            table.ajax.reload(null, false);
          } else {
            Swal.fire("Error", res.message, "error");
          }
        })
        .catch(() => Swal.fire("Error", "Something went wrong.", "error"));
    });
  });

  $("#BLtable").on("click", ".bl-cancel-btn", function () {
    const id = $(this).data("id");

    Swal.fire({
      title: "Cancel this request?",
      text: "This cannot be undone.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, cancel it",
    }).then((result) => {
      if (!result.isConfirmed) return;

      fetch("functions/cancel_blacklist_request.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
      })
        .then((r) => r.json())
        .then((res) => {
          if (res.success) {
            Swal.fire("Cancelled", res.message, "success");
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
  const viewModalEl = document.getElementById("viewBlacklistRequestModal");
  const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;

  $("#BLtable tbody").on("click", "tr", function (e) {
    if (
      $(e.target).closest(
        ".bl-actions-col, .bl-approve-btn, .bl-reject-btn, .bl-cancel-btn",
      ).length
    ) {
      return;
    }

    const rowData = table.row(this).data();
    if (!rowData) return;

    populateViewModal(rowData);
    viewModal.show();
  });

  let currentViewRequestId = null;

  function populateViewModal(r) {
    $("#vbr_full_name").text(r.full_name || "—");
    $("#vbr_status_badge").html(statusBadge(r.status));
    $("#vbr_birthday").text(
      r.birthday ? new Date(r.birthday).toLocaleDateString() : "—",
    );
    $("#vbr_branch").text(r.branch || "—");
    $("#vbr_brand").text(r.brand || "—");
    $("#vbr_employment_status").text(r.employment_status || "—");
    $("#vbr_end_date").text(
      r.end_date ? new Date(r.end_date).toLocaleDateString() : "—",
    );
    $("#vbr_requested_by").text(r.requested_by || "—");
    $("#vbr_requested_date").text(
      r.requested_date ? new Date(r.requested_date).toLocaleString() : "—",
    );
    $("#vbr_approved_by").text(r.approved_by || "—");
    $("#vbr_approved_date").text(
      r.approved_date ? new Date(r.approved_date).toLocaleString() : "—",
    );
    $("#vbr_remarks").text(
      r.remarks && r.remarks.trim() ? r.remarks : "No remarks provided.",
    );

    // Reset attachment section for this open
    currentViewRequestId = r.id;
    $("#vbr_attachments").empty();
    $("#vbr_attachments_wrapper").addClass("d-none");
    $("#vbr_view_attachments_btn")
      .toggleClass("d-none", !canViewAttachments(r))
      .html('<i class="bi bi-paperclip me-1"></i>View Attachments')
      .prop("disabled", false);
  }

  $("#vbr_view_attachments_btn").on("click", function () {
    const $btn = $(this);
    const $wrapper = $("#vbr_attachments_wrapper");

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
    const $container = $("#vbr_attachments").empty().text("Loading...");

    fetch(
      "functions/fetch_blacklist_attachments.php?request_id=" +
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
  // Request Blacklist modal
  // ---------------------------------------------------------------
  const requestModalEl = document.getElementById("requestBlacklistModal");
  const requestModal = requestModalEl
    ? new bootstrap.Modal(requestModalEl)
    : null;
  const IS_BRANCH_MANAGER =
    (CURRENT_USER_ROLE || "").toLowerCase() === "branch_manager";
  let selectedEmployee = null;

  // ---------------------------------------------------------------
  // Attachments (max 3 images) for the Request Blacklist form
  // ---------------------------------------------------------------
  const MAX_ATTACHMENTS = 3;
  const MAX_ATTACHMENT_MB = 5;
  let selectedAttachments = []; // array of File

  $("#bl_attachments_input").on("change", function () {
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
    const $preview = $("#bl_attachments_preview").empty();

    selectedAttachments.forEach((file, idx) => {
      const url = URL.createObjectURL(file);
      const $thumb = $(`
        <div class="position-relative" style="width:80px;">
          <img src="${url}" class="rounded border" style="width:80px;height:80px;object-fit:cover;">
          <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0
                      bl-remove-attachment" data-idx="${idx}"
                  style="width:20px;height:20px;line-height:1;">&times;</button>
        </div>
      `);
      $preview.append($thumb);
    });

    $("#bl_attachments_input").prop(
      "disabled",
      selectedAttachments.length >= MAX_ATTACHMENTS,
    );
  }

  $("#bl_attachments_preview").on(
    "click",
    ".bl-remove-attachment",
    function () {
      const idx = $(this).data("idx");
      selectedAttachments.splice(idx, 1);
      renderAttachmentPreviews();
    },
  );

  $("#openRequestBlacklistBtn").on("click", function () {
    resetRequestForm();
    populateBranchDropdown();
    requestModal.show();
  });

  function resetRequestForm() {
    selectedEmployee = null;
    selectedAttachments = [];
    $("#bl_attachments_preview").empty();
    $("#bl_attachments_input").val("").prop("disabled", false);
    $("#bl_branch_select").empty();
    $("#bl_employee_select")
      .empty()
      .append('<option value="">Select a branch first...</option>')
      .prop("disabled", true);
    [
      "first_name",
      "middle_name",
      "last_name",
      "suffix",
      "birthday",
      "gender",
      "marital_status",
      "branch",
      "brand",
      "employment_status",
    ].forEach((f) => $("#bl_" + f).val(""));
    $("#bl_branch_code").val("");
    $("#bl_employee_id").val("");
    $("#bl_end_date").val("");
    $("#bl_remarks").val("");
    $("#submitBlacklistRequestBtn").prop("disabled", true);
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
  // blacklist_request.branch actually store codes.
  function populateBranchDropdown() {
    const $select = $("#bl_branch_select").empty();

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
          loadEmployeesForBranch(myCode);
        })
        .catch(() => {
          $select
            .empty()
            .append(`<option value="${myCode}">${myCode}</option>`);
          $select.prop("disabled", true);
          loadEmployeesForBranch(myCode);
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

  $("#bl_branch_select").on("change", function () {
    const branchCode = $(this).val();
    clearEmployeeFields();
    if (branchCode) {
      loadEmployeesForBranch(branchCode);
    } else {
      $("#bl_employee_select")
        .empty()
        .append('<option value="">Select a branch first...</option>')
        .prop("disabled", true);
    }
  });

  function loadEmployeesForBranch(branchCode) {
    const $empSelect = $("#bl_employee_select")
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

  $("#bl_employee_select").on("change", function () {
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
      "birthday",
      "gender",
      "marital_status",
      "branch",
      "brand",
      "employment_status",
    ].forEach((f) => $("#bl_" + f).val(""));
    $("#bl_branch_code").val("");
    $("#bl_employee_id").val("");
    $("#submitBlacklistRequestBtn").prop("disabled", true);
  }

  function selectEmployee(emp) {
    selectedEmployee = emp;

    $("#bl_employee_id").val(emp.employee_id);
    $("#bl_first_name").val(emp.first_name);
    $("#bl_middle_name").val(emp.middle_name);
    $("#bl_last_name").val(emp.last_name);
    $("#bl_suffix").val(emp.suffix);
    $("#bl_birthday").val(emp.birthday ? emp.birthday.split("T")[0] : "");
    $("#bl_gender").val(emp.gender);
    $("#bl_marital_status").val(emp.marital_status);
    $("#bl_branch").val(emp.branch); // display name
    $("#bl_branch_code").val(emp.branch_code); // actual value submitted/stored
    $("#bl_brand").val(emp.brand);
    $("#bl_employment_status").val(emp.employment_status);

    $("#submitBlacklistRequestBtn").prop("disabled", false);
  }

  $("#submitBlacklistRequestBtn").on("click", function () {
    if (!selectedEmployee) return;

    if (!$("#bl_remarks").val().trim()) {
      Swal.fire(
        "Remarks required",
        "Please provide a reason for this blacklist request.",
        "warning",
      );
      return;
    }

    const $btn = $(this);
    if ($btn.prop("disabled")) return; // already submitting — ignore extra clicks
    $btn.prop("disabled", true);

    const formData = new FormData();
    formData.append("employee_id", $("#bl_employee_id").val());
    formData.append("first_name", $("#bl_first_name").val());
    formData.append("middle_name", $("#bl_middle_name").val());
    formData.append("last_name", $("#bl_last_name").val());
    formData.append("suffix", $("#bl_suffix").val());
    formData.append("birthday", $("#bl_birthday").val());
    formData.append("gender", $("#bl_gender").val());
    formData.append("marital_status", $("#bl_marital_status").val());
    formData.append("branch", $("#bl_branch_code").val()); // store the code, not the display name
    formData.append("brand", $("#bl_brand").val());
    formData.append("employment_status", $("#bl_employment_status").val());
    formData.append("end_date", $("#bl_end_date").val());
    formData.append("remarks", $("#bl_remarks").val());

    selectedAttachments.forEach((file) =>
      formData.append("attachments[]", file),
    );

    fetch("functions/submit_blacklist_request.php", {
      method: "POST",
      body: formData, // no Content-Type header — browser sets the multipart boundary
    })
      .then((r) => r.json())
      .then((res) => {
        if (res.success) {
          Swal.fire("Submitted", res.message, "success");
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
