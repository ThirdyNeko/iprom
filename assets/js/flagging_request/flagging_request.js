$(function () {
  // Role helpers. For regional_manager, flagging_request.php passes
  // CURRENT_USER_BRANCH as a comma-joined list of every branch code in their
  // region (resolved at login), so the same list doubles as their region scope.
  const CURRENT_ROLE = (CURRENT_USER_ROLE || "").toLowerCase();
  const IS_REGIONAL_MANAGER = CURRENT_ROLE === "regional_manager";

  // Normalize any branch code before comparing (handles CHAR padding,
  // numbers vs strings, null/undefined).
  const normCode = (v) => String(v ?? "").trim();

  const REGION_BRANCH_CODES = (CURRENT_USER_BRANCH || "")
    .split(",")
    .map(normCode)
    .filter(Boolean);

  // Attachment cap for a single request (existing + newly added).
  const MAX_TOTAL_ATTACHMENTS = 3;

  // Once an audit role was the last to touch a request, the branch-side
  // roles are locked out of adding attachments and unflagging it.
  const AUDIT_ROLES = ["audit_manager", "audit_supervisor"];
  const BRANCH_SIDE_ROLES = ["branch_manager", "regional_manager"];

  // Client-side check is for showing/hiding buttons only — the SQL proc /
  // PHP endpoints re-verify this independently.
  function isLockedByAudit(r) {
    return (
      BRANCH_SIDE_ROLES.includes(CURRENT_ROLE) &&
      AUDIT_ROLES.includes((r.last_updated_by_role || "").toLowerCase())
    );
  }

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
  //   - regional_manager can unflag requests submitted by a branch_manager
  //     whose branch is inside their region
  // EXCEPT: branch_manager / regional_manager can never unflag a request
  // whose last_updated_by is an audit_manager / audit_supervisor.
  // Client-side check is for showing/hiding the button only —
  // unflag_request.php / the SQL proc re-verify this independently
  // before actually unflagging.
  // Who last touched the request. Falls back to the requester for rows
  // that have never been updated (in case last_updated_* comes back null).
  function getLastUpdater(r) {
    return {
      name: r.last_updated_by || r.requested_by,
      role: (r.last_updated_by_role || r.requester_role || "").toLowerCase(),
    };
  }

  // Branch-side roles must never see requests last updated by audit.
  function isHiddenFromCurrentRole(r) {
    return (
      BRANCH_SIDE_ROLES.includes(CURRENT_ROLE) &&
      AUDIT_ROLES.includes(getLastUpdater(r).role)
    );
  }

  // Who can unflag a given row, based on the LAST UPDATER:
  //   - the last updater themself
  //   - audit_manager: last updated by audit_manager / audit_supervisor
  //   - admin/super_admin: last updated by branch_manager
  //   - regional_manager: last updated by branch_manager, branch in their region
  // Client-side check is for showing/hiding the button only —
  // unflag_request.php / the SQL proc re-verify this independently.
  function canUnflagRow(r) {
    if (isHiddenFromCurrentRole(r)) return false;

    const role = CURRENT_ROLE;
    const last = getLastUpdater(r);
    const rowBranch = normCode(r.branch_code ?? r.branch);

    const isLastUpdater = last.name === CURRENT_USER_NAME;

    const isAuditManagerOverAudit =
      role === "audit_manager" && AUDIT_ROLES.includes(last.role);

    const isAdminOverBranchManager =
      (role === "admin" ||
        role === "super_admin" ||
        role === "assistant_admin") &&
      (last.role === "branch_manager" || last.role === "regional_manager");

    const isRegionalOverBranchManager =
      IS_REGIONAL_MANAGER &&
      last.role === "branch_manager" &&
      REGION_BRANCH_CODES.includes(rowBranch);

    return (
      isLastUpdater ||
      isAuditManagerOverAudit ||
      isAdminOverBranchManager ||
      isRegionalOverBranchManager
    );
  }

  // Attachments are visible to: the requester themself, admin/super_admin,
  // and audit_manager / audit_supervisor. Client-side check is for
  // showing/hiding the button only — fetch_flagging_attachments.php
  // re-verifies this before returning any image data.
  function canViewAttachments(r) {
    const isOwner = r.requested_by === CURRENT_USER_NAME;
    const isAdmin =
      CURRENT_ROLE === "admin" ||
      CURRENT_ROLE === "super_admin" ||
      CURRENT_ROLE === "assistant_admin";
    const isAudit = AUDIT_ROLES.includes(CURRENT_ROLE);
    return isOwner || isAdmin || isAudit;
  }

  // Adding attachments to an existing request: only while it's Flagged and
  // only for users who can request flagging. branch_manager / regional_manager
  // are additionally locked out once audit_manager / audit_supervisor was the
  // last to update the request (see isLockedByAudit).
  // add_flagging_attachments.php re-verifies all of this, including the
  // 3-attachment cap.
  function canAddAttachments(r) {
    if (r.status !== "Flagged" || !CAN_REQUEST_FLAGGING) return false;
    return !isLockedByAudit(r);
  }

  if (CAN_ACTION_FLAGGING_REQUESTS || CAN_REQUEST_FLAGGING) {
    columns.push({
      data: null,
      orderable: false,
      className: "fr-actions-col",
      render: (r) => {
        let html = "";

        if (r.status === "Flagged" && canUnflagRow(r)) {
          html += `<button class="btn btn-outline-danger btn-sm fr-unflag-btn" data-id="${r.id}">Unflag</button> `;
        }

        if (CAN_ACTION_FLAGGING_REQUESTS && r.status !== "Blacklisted") {
          html += `<button class="btn btn-purple btn-sm fr-blacklist-btn" data-id="${r.id}">Blacklist</button>`;
        }

        return html || `<span class="text-muted">—</span>`;
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
        : status === "Blacklisted"
          ? "status-badge-blacklisted"
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

  $("#FRtable").on("click", ".fr-blacklist-btn", function () {
    const id = $(this).data("id");

    Swal.fire({
      title: "Blacklist this employee?",
      text: "This removes linked roving / multi-brand records and adds them to the blacklist. This cannot be undone.",
      icon: "warning",
      width: "600px",
      confirmButtonColor: "#212529",
      html: `
      <textarea id="swal-bl-remarks"
                class="swal2-textarea"
                maxlength="100"
                placeholder="Remarks (required)..."
                style="margin:10px 0 0 0; width:100%; height:100px; font-size:16px; resize:none;"></textarea>
      <div class="text-end text-muted" style="font-size:12px;">
        <span id="swal-bl-remarks-count">0</span>/100
      </div>
    `,
      didOpen: () => {
        const textarea = document.getElementById("swal-bl-remarks");
        const counter = document.getElementById("swal-bl-remarks-count");
        textarea.addEventListener("input", () => {
          counter.textContent = textarea.value.length;
        });
        textarea.focus();
      },
      preConfirm: () => {
        const value = document.getElementById("swal-bl-remarks").value.trim();
        if (!value) {
          Swal.showValidationMessage("Remarks are required.");
          return false;
        }
        return value;
      },
      showCancelButton: true,
      confirmButtonText: "Blacklist",
    }).then((result) => {
      if (!result.isConfirmed) return;

      fetch("functions/blacklist_request.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, remarks: result.value }),
      })
        .then((r) => r.json())
        .then((res) => {
          if (res.success) {
            Swal.fire("Employee blacklisted", "", "success");
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
    if ($(e.target).closest(".fr-actions-col, .fr-unflag-btn").length) return;

    const rowData = table.row(this).data();
    if (!rowData || isHiddenFromCurrentRole(rowData)) return;

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
  let currentViewRow = null; // the row object behind the open view modal

  // Falls back to the requester/requested date for rows never updated,
  // same as getLastUpdater().
  function renderLastUpdated(r) {
    const last = getLastUpdater(r);
    const dt = r.last_updated_date || r.requested_date;
    $("#vfr_last_updated_by").text(last.name || "—");
    $("#vfr_last_updated_date").text(dt ? new Date(dt).toLocaleString() : "—");
  }

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
    renderLastUpdated(r);
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
    currentViewRow = r;
    $("#vfr_attachments").empty();
    $("#vfr_attachments_wrapper").addClass("d-none");
    $("#vfr_view_attachments_btn")
      .toggleClass("d-none", !canViewAttachments(r))
      .html('<i class="bi bi-paperclip me-1"></i>View Attachments')
      .prop("disabled", false);

    refreshAddAttachmentUI();
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
  // Add attachments to an existing request (from the view modal)
  //   - max MAX_TOTAL_ATTACHMENTS per request, counting what's already
  //     attached (r.attachment_count comes from the list SP)
  //   - Add button is disabled once the request is at the cap
  //   - hidden entirely when canAddAttachments() is false (not Flagged,
  //     or locked because audit was the last updater)
  // ---------------------------------------------------------------
  function refreshAddAttachmentUI() {
    const r = currentViewRow;
    if (!r) return;

    // If the list endpoint doesn't send attachment_count, don't silently
    // treat it as 0 (that would let people add past the cap) — disable
    // the button and say why.
    const countKnown =
      r.attachment_count !== undefined && r.attachment_count !== null;
    if (!countKnown) {
      console.warn(
        "attachment_count missing from the flagging request row — add it to get_flagging_requests / fetch_flagging_requests.php",
      );
    }

    const count = Number(r.attachment_count) || 0;
    const atCap = count >= MAX_TOTAL_ATTACHMENTS;

    $("#vfr_add_attachments_wrapper").toggleClass(
      "d-none",
      !canAddAttachments(r),
    );
    $("#vfr_attachment_count").text(
      countKnown
        ? `${count}/${MAX_TOTAL_ATTACHMENTS}`
        : "?/" + MAX_TOTAL_ATTACHMENTS,
    );
    $("#vfr_add_attachments_btn")
      .prop("disabled", atCap || !countKnown)
      .attr(
        "title",
        !countKnown
          ? "Attachment count unavailable"
          : atCap
            ? "Maximum of 3 attachments reached"
            : "",
      );
  }

  $("#vfr_add_attachments_btn").on("click", function () {
    $("#vfr_add_attachments_input").trigger("click");
  });

  $("#vfr_add_attachments_input").on("change", function () {
    const r = currentViewRow;
    const files = Array.from(this.files || []);
    this.value = ""; // allow re-selecting the same file

    if (!r || !files.length || !canAddAttachments(r)) return;

    const remaining = MAX_TOTAL_ATTACHMENTS - (Number(r.attachment_count) || 0);
    if (remaining <= 0) {
      Swal.fire(
        "Limit reached",
        `A request can have at most ${MAX_TOTAL_ATTACHMENTS} attachments.`,
        "warning",
      );
      return;
    }
    if (files.length > remaining) {
      Swal.fire(
        "Too many images",
        `You can add ${remaining} more image${remaining === 1 ? "" : "s"} to this request.`,
        "warning",
      );
      return;
    }
    for (const file of files) {
      if (!/^image\/(png|jpe?g)$/.test(file.type)) {
        Swal.fire(
          "Unsupported file",
          `"${file.name}" isn't a supported image type.`,
          "warning",
        );
        return;
      }
      if (file.size > MAX_ATTACHMENT_MB * 1024 * 1024) {
        Swal.fire(
          "File too large",
          `"${file.name}" exceeds ${MAX_ATTACHMENT_MB}MB.`,
          "warning",
        );
        return;
      }
    }

    Swal.fire({
      title: `Upload ${files.length} image${files.length === 1 ? "" : "s"}?`,
      text: "Attachments can't be removed once added.",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Upload",
    }).then((result) => {
      if (!result.isConfirmed) return;

      const fd = new FormData();
      fd.append("request_id", r.id);
      files.forEach((f) => fd.append("attachments[]", f));

      $("#vfr_add_attachments_btn").prop("disabled", true);

      fetch("functions/add_flagging_attachments.php", {
        method: "POST",
        body: fd, // no Content-Type header — browser sets the multipart boundary
      })
        .then((res) => res.json())
        .then((res) => {
          if (!res.success) {
            Swal.fire("Error", res.message || "Upload failed.", "error");
            return;
          }

          // Keep the modal's copy of the row in sync with what the server did
          r.attachment_count = res.attachment_count;
          r.last_updated_by = CURRENT_USER_NAME;
          r.last_updated_by_role = CURRENT_ROLE;
          r.last_updated_date = new Date().toISOString();
          renderLastUpdated(r);

          Swal.fire("Attachments added", "", "success");

          // Refresh the gallery if it's currently open
          if (!$("#vfr_attachments_wrapper").hasClass("d-none")) {
            loadViewAttachments(r.id);
          }
          table.ajax.reload(null, false);
        })
        .catch(() => Swal.fire("Error", "Something went wrong.", "error"))
        .finally(() => refreshAddAttachmentUI());
    });
  });

  // ---------------------------------------------------------------
  // Request Flagging modal
  // ---------------------------------------------------------------
  const requestModalEl = document.getElementById("requestFlaggingModal");
  const requestModal = requestModalEl
    ? new bootstrap.Modal(requestModalEl)
    : null;
  const IS_BRANCH_MANAGER = CURRENT_ROLE === "branch_manager";
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
  // - regional_manager: limited to the branches in their region (the codes
  //   from CURRENT_USER_BRANCH), selectable. Same server-side caveat.
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
        .map(normCode)
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
          // Guard against an error object, and compare normalized codes
          const list = Array.isArray(branches) ? branches : [];
          const match = list.find((b) => normCode(b.branch_code) === myCode);
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

    if (IS_REGIONAL_MANAGER) {
      // No branches = nothing to pick, never "everything"
      if (!REGION_BRANCH_CODES.length) {
        $select
          .append('<option value="">No branches in your region</option>')
          .prop("disabled", true);
        return;
      }

      $select
        .append('<option value="">Loading branches...</option>')
        .prop("disabled", true);

      fetch("functions/fetch_all_branches.php")
        .then((r) => r.json())
        .then((branches) => {
          $select.empty();

          // A failed/non-array response is a load error, not "no branches"
          if (!Array.isArray(branches)) {
            console.error("fetch_all_branches.php returned:", branches);
            $select
              .append('<option value="">Failed to load branches</option>')
              .prop("disabled", true);
            return;
          }

          // Normalize the endpoint's codes before matching
          const mine = branches.filter((b) =>
            REGION_BRANCH_CODES.includes(normCode(b.branch_code)),
          );

          if (!mine.length) {
            // Helps diagnose a code-format mismatch between the two lists
            console.warn(
              "No region match. REGION_BRANCH_CODES:",
              REGION_BRANCH_CODES,
              "sample endpoint code:",
              branches[0] && JSON.stringify(branches[0].branch_code),
            );
            $select
              .append('<option value="">No branches in your region</option>')
              .prop("disabled", true);
            return;
          }

          $select.append('<option value="">Select branch...</option>');
          mine.forEach((b) =>
            $select.append(
              `<option value="${normCode(b.branch_code)}">${b.branch}</option>`,
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

        if (!Array.isArray(branches) || !branches.length) {
          $select
            .append('<option value="">No branches available</option>')
            .prop("disabled", true);
          return;
        }

        $select.append('<option value="">Select branch...</option>');
        branches.forEach((b) =>
          $select.append(
            `<option value="${normCode(b.branch_code)}">${b.branch}</option>`,
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
