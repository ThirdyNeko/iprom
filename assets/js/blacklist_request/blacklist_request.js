$(function () {
  // ---------------------------------------------------------------
  // DataTable init — server-side processing, matching the
  // get_blacklist_requests SP (ROW_NUMBER paging, COUNT(*) OVER total).
  // Column order below MUST match fetch_blacklist_requests.php's
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

  if (CAN_ACTION_REQUESTS) {
    columns.push({
      data: null,
      orderable: false,
      render: (r) => {
        if (r.status !== "Pending") {
          return `<span class="text-muted">—</span>`;
        }
        return `
                    <button class="btn btn-success btn-sm bl-approve-btn" data-id="${r.id}">
                        <i class="bi bi-check-lg"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm bl-reject-btn" data-id="${r.id}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                `;
      },
    });
  }

  const table = $("#BLtable").DataTable({
    serverSide: true,
    processing: true,
    ajax: {
      url: "functions/fetch_blacklist_request.php",
      type: "GET",
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

  // ---------------------------------------------------------------
  // Approve / Reject
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

  $("#openRequestBlacklistBtn").on("click", function () {
    resetRequestForm();
    populateBranchDropdown();
    requestModal.show();
  });

  function resetRequestForm() {
    selectedEmployee = null;
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
    const branch = $(this).val();
    clearEmployeeFields();
    if (branch) {
      loadEmployeesForBranch(branch);
    } else {
      $("#bl_employee_select")
        .empty()
        .append('<option value="">Select a branch first...</option>')
        .prop("disabled", true);
    }
  });

  function loadEmployeesForBranch(branch) {
    const $empSelect = $("#bl_employee_select")
      .empty()
      .append('<option value="">Loading...</option>')
      .prop("disabled", true);

    fetch(
      "functions/fetch_branch_employees.php?branch=" +
        encodeURIComponent(branch),
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
            `<option value="${emp.employee_id}">${emp.first_name} ${emp.last_name} (${emp.employee_id})</option>`,
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

    const payload = {
      employee_id: $("#bl_employee_id").val(),
      first_name: $("#bl_first_name").val(),
      middle_name: $("#bl_middle_name").val(),
      last_name: $("#bl_last_name").val(),
      suffix: $("#bl_suffix").val(),
      birthday: $("#bl_birthday").val(),
      gender: $("#bl_gender").val(),
      marital_status: $("#bl_marital_status").val(),
      branch: $("#bl_branch_code").val(), // store the code, not the display name
      brand: $("#bl_brand").val(),
      employment_status: $("#bl_employment_status").val(),
      end_date: $("#bl_end_date").val(),
      remarks: $("#bl_remarks").val(),
    };

    if (!payload.remarks.trim()) {
      Swal.fire(
        "Remarks required",
        "Please provide a reason for this blacklist request.",
        "warning",
      );
      return;
    }

    fetch("functions/submit_blacklist_request.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then((r) => r.json())
      .then((res) => {
        if (res.success) {
          Swal.fire("Submitted", res.message, "success");
          requestModal.hide();
          table.ajax.reload(null, false);
        } else {
          Swal.fire("Error", res.message, "error");
        }
      })
      .catch(() => Swal.fire("Error", "Something went wrong.", "error"));
  });
});
