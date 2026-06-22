$(document).ready(function () {
  let originalAgencyValues = null;

  // =========================
  // DATATABLE
  // =========================
  window.table = $("#agencyTable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    ordering: false,
    responsive: true,
    dom: "lrtip",

    ajax: {
      url: "functions/fetch_agencies.php",
      type: "POST",
      data: function (d) {
        d.name = $("#filterName").val();
      },
    },

    columns: [
      { data: "agencies" },
      { data: "contact_person" },
      { data: "contact_number" },
      { data: "tel_number" },
      { data: "email" },

      // STATUS SWITCH
      {
        data: null,
        width: "120px",
        className: "text-center",
        render: function (data, type, row) {
          const isActive = String(row.status) === "1";

          return `
            <div class="d-flex align-items-center justify-content-center gap-1">

              <span class="badge ${isActive ? "bg-success" : "bg-secondary"}">
                ${isActive ? "Active" : "Inactive"}
              </span>

              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input agency-status-switch"
                  type="checkbox"
                  data-id="${row.id}"
                  ${isActive ? "checked" : ""}
                >
              </div>

            </div>
          `;
        },
      },

      // ACTIONS
      {
        data: null,
        width: "90px",
        className: "text-center px-1",
        orderable: false,
        render: function (data) {
          return `
            <div class="action-btns d-flex justify-content-center">
              <button class="btn btn-warning btn-sm px-2 py-1 editAgencyBtn"
                data-id="${data.id}"
                data-name="${data.agencies}"
                data-person="${data.contact_person}"
                data-number="${data.contact_number}"
                data-tel="${data.tel_number}"
                data-email="${data.email}">
                Update
              </button>
            </div>
          `;
        },
      },
    ],
  });

  $("#filterName").on("input", function () {
    table.draw();
  });

  // =========================
  // OPEN ADD MODAL
  // =========================
  $(document).on("click", ".addAgencyBtn", function () {
    $("#agencyId").val("");

    $("#agencyName").val("").trigger("input");
    $("#contactPerson").val("").trigger("input");
    $("#email").val("");

    // RESET MOBILES
    $("#mobileContainer").html(`
      <div class="input-group mb-2">
        <input type="text"
               name="contact_numbers[]"
               class="form-control mobile-input"
               placeholder="09XX XXX XXXX"
               maxlength="13">
      </div>
    `);

    // RESET TELEPHONES
    $("#telephoneContainer").html(`
      <div class="input-group mb-2">
        <input type="text"
               name="tel_numbers[]"
               class="form-control telephone-input"
               placeholder="(XXX) XXX-XX-XX"
               maxlength="15">
      </div>
    `);

    $(".modal-title").text("Add Agency");

    $("#saveAgencyBtn")
      .text("Add Agency")
      .removeClass("btn-warning")
      .addClass("btn-primary")
      .prop("disabled", false);

    $("#agencyModal").modal("show");
  });

  // =========================
  // EDIT
  // =========================
  $(document).on("click", ".editAgencyBtn", function () {
    $("#agencyId").val($(this).data("id"));
    $("#agencyName").val($(this).data("name"));
    $("#contactPerson").val($(this).data("person"));
    $("#email").val($(this).data("email"));

    // =========================
    // MOBILE NUMBERS
    // =========================
    let mobileNumbers = ($(this).data("number") || "").split("|");

    $("#mobileContainer").html("");

    mobileNumbers.forEach((num, index) => {
      $("#mobileContainer").append(`
        <div class="input-group mb-2">
          <input type="text"
                 name="contact_numbers[]"
                 class="form-control mobile-input"
                 value="${num.trim()}"
                 placeholder="09XX XXX XXXX"
                 maxlength="13">

          ${
            index > 0
              ? `
            <button type="button"
                    class="btn btn-outline-danger remove-mobile">
              <i class="bi bi-x-lg"></i>
            </button>
          `
              : ""
          }
        </div>
      `);
    });

    // =========================
    // TELEPHONE NUMBERS
    // =========================
    let telNumbers = ($(this).data("tel") || "").split("|");

    $("#telephoneContainer").html("");

    telNumbers.forEach((num, index) => {
      $("#telephoneContainer").append(`
        <div class="input-group mb-2">
          <input type="text"
                 name="tel_numbers[]"
                 class="form-control telephone-input"
                 value="${num.trim()}"
                 placeholder="(XXX) XXX-XX-XX"
                 maxlength="15">

          ${
            index > 0
              ? `
            <button type="button"
                    class="btn btn-outline-danger remove-telephone">
              <i class="bi bi-x-lg"></i>
            </button>
          `
              : ""
          }
        </div>
      `);
    });

    $(".modal-title").text("Update Agency");

    $("#saveAgencyBtn")
      .text("Save Changes")
      .removeClass("btn-primary")
      .addClass("btn-warning")
      .prop("disabled", true);

    // Store originals for change detection
    originalAgencyValues = {
      name: $(this).data("name") || "",
      person: $(this).data("person") || "",
      email: $(this).data("email") || "",
      mobiles: ($(this).data("number") || "")
        .split("|")
        .map((s) => s.trim())
        .filter(Boolean),
      tels: ($(this).data("tel") || "")
        .split("|")
        .map((s) => s.trim())
        .filter(Boolean),
    };

    $("#agencyModal").modal("show");
  });

  // =========================
  // RESET ON MODAL CLOSE
  // =========================
  $("#agencyModal").on("hidden.bs.modal", function () {
    $("#agencyId").val("");

    $("#agencyName").val("");
    $("#contactPerson").val("");
    $("#email").val("");

    $("#mobileContainer").html(`
      <div class="input-group mb-2">
        <input type="text"
               name="contact_numbers[]"
               class="form-control mobile-input"
               placeholder="09XX XXX XXXX"
               maxlength="13">
      </div>
    `);

    $("#telephoneContainer").html(`
      <div class="input-group mb-2">
        <input type="text"
               name="tel_numbers[]"
               class="form-control telephone-input"
               placeholder="(XXX) XXX-XX-XX"
               maxlength="15">
      </div>
    `);

    $(".modal-title").text("Add Agency");

    $("#saveAgencyBtn")
      .text("Save Agency")
      .removeClass("btn-warning")
      .addClass("btn-primary")
      .prop("disabled", false);

    originalAgencyValues = null;
  });

  // =========================
  // FORCE UPPERCASE
  // =========================
  $(document).on("input", "#agencyName, #contactPerson", function () {
    this.value = this.value.toUpperCase();
  });

  // =========================
  // CHANGE DETECTION (EDIT MODE)
  // =========================
  function getAgencyFormValues() {
    return {
      name: $("#agencyName").val().trim().toUpperCase(),
      person: $("#contactPerson").val().trim().toUpperCase(),
      email: $("#email").val().trim(),
      mobiles: $("input[name='contact_numbers[]']")
        .map(function () {
          return $(this).val().trim();
        })
        .get()
        .filter(Boolean),
      tels: $("input[name='tel_numbers[]']")
        .map(function () {
          return $(this).val().trim();
        })
        .get()
        .filter(Boolean),
    };
  }

  function checkAgencyChanges() {
    if (!originalAgencyValues) return;

    const current = getAgencyFormValues();

    const changed =
      current.name !== originalAgencyValues.name ||
      current.person !== originalAgencyValues.person ||
      current.email !== originalAgencyValues.email ||
      current.mobiles.join("|") !== originalAgencyValues.mobiles.join("|") ||
      current.tels.join("|") !== originalAgencyValues.tels.join("|");

    $("#saveAgencyBtn").prop("disabled", !changed);
  }

  // Bind to text fields
  $(document).on("input", "#agencyName, #contactPerson, #email", function () {
    checkAgencyChanges();
  });

  // Bind to dynamic mobile/telephone inputs
  $(document).on(
    "input",
    "input[name='contact_numbers[]'], input[name='tel_numbers[]']",
    function () {
      checkAgencyChanges();
    },
  );

  // Re-check after removing a mobile row
  $(document).on("click", ".remove-mobile", function () {
    $(this).closest(".input-group").remove();
    checkAgencyChanges();
  });

  // Re-check after removing a telephone row
  $(document).on("click", ".remove-telephone", function () {
    $(this).closest(".input-group").remove();
    checkAgencyChanges();
  });

  // =========================
  // SAVE (FIXED INSERT/UPDATE LOGIC)
  // =========================
  $("#agencyForm").on("submit", function (e) {
    e.preventDefault();

    const id = $("#agencyId").val();

    const agency = $("#agencyName").val()?.trim().toUpperCase() || "";
    const contact_person =
      $("#contactPerson").val()?.trim().toUpperCase() || "";
    const contact_numbers = $("input[name='contact_numbers[]']")
      .map(function () {
        return $(this).val().trim();
      })
      .get()
      .filter((v) => v !== "");

    const tel_numbers = $("input[name='tel_numbers[]']")
      .map(function () {
        return $(this).val().trim();
      })
      .get()
      .filter((v) => v !== "");
    const email = $("#email").val()?.trim() || "";

    const isEdit = id && id !== "";

    if (!agency || !contact_person || contact_numbers.length === 0 || !email) {
      Swal.fire({
        icon: "warning",
        title: "Required",
        text: "All fields are required.",
      });
      return;
    }

    Swal.fire({
      title: isEdit ? "Update this agency?" : "Add this agency?",
      text: agency,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#2d68c4",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes",
    }).then((result) => {
      if (!result.isConfirmed) return;

      submitAgency(
        id,
        agency,
        contact_person,
        contact_numbers,
        tel_numbers,
        email,
        isEdit,
      );
    });
  });

  // =========================
  // AJAX SAVE
  // =========================
  function submitAgency(
    id,
    agency,
    contact_person,
    contact_numbers,
    tel_numbers,
    email,
    isEdit,
  ) {
    $.ajax({
      url: "functions/save_agency.php",
      type: "POST",
      dataType: "json",
      data: {
        id,
        agency,
        contact_person,
        contact_numbers,
        tel_numbers,
        email,
      },

      beforeSend: function () {
        Swal.fire({
          title: isEdit ? "Updating..." : "Saving...",
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading(),
        });
      },

      success: function (res) {
        Swal.close();

        if (res.success) {
          Swal.fire({
            icon: "success",
            title: isEdit ? "Updated!" : "Added!",
            text: res.message,
            timer: 1200,
            showConfirmButton: false,
          });

          $("#agencyModal").modal("hide");

          window.table.ajax.reload(null, false);
        } else {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: res.message || "Operation failed.",
          });
        }
      },

      error: function () {
        Swal.close();

        Swal.fire({
          icon: "error",
          title: "Server Error",
          text: "Something went wrong.",
        });
      },
    });
  }
});

// =========================
// STATUS SWITCH (AGENCY)
// =========================
$(document).on("change", ".agency-status-switch", function () {
  const toggle = $(this);
  const id = toggle.data("id");
  const status = toggle.is(":checked") ? 1 : 0;

  const rowData = window.table.row(toggle.closest("tr")).data();
  const agency = rowData?.agencies;

  toggle.prop("disabled", true);

  $.ajax({
    url: "functions/check_agency_employees.php",
    type: "POST",
    dataType: "json",
    data: { agency: agency },

    success: function (check) {
      if (check.blocked) {
        toggle.prop("checked", !toggle.is(":checked"));

        const employees = (check.employees || []).join(", ");

        Swal.fire({
          icon: "warning",
          title: "Cannot Update Agency Status",
          text: employees
            ? `A promodiser is currently assigned and must be removed or reassigned before proceeding.`
            : "This agency has active promodizers.",
        });

        toggle.prop("disabled", false);
        return;
      }

      $.ajax({
        url: "functions/update_agency_status.php",
        type: "POST",
        dataType: "json",
        data: {
          id: id,
          status: status,
        },

        success: function (res) {
          if (res.success) {
            const row = window.table.row(toggle.closest("tr"));

            if (row) {
              const data = row.data();

              row
                .data({
                  ...data,
                  status: status.toString(),
                })
                .invalidate();
            }

            Swal.fire({
              icon: "success",
              title: "Updated",
              text: `Agency is now ${status === 1 ? "Active" : "Inactive"}`,
              timer: 1200,
              showConfirmButton: false,
            });
          } else {
            toggle.prop("checked", !toggle.is(":checked"));
            Swal.fire({
              icon: "error",
              title: "Update Failed",
              text: res.message,
            });
          }
        },

        error: function () {
          toggle.prop("checked", !toggle.is(":checked"));
          Swal.fire({ icon: "error", title: "Server Error" });
        },

        complete: function () {
          toggle.prop("disabled", false);
        },
      });
    },

    error: function () {
      toggle.prop("checked", !toggle.is(":checked"));
      Swal.fire({
        icon: "error",
        title: "Server Error",
        text: "Failed to validate agency employees.",
      });

      toggle.prop("disabled", false);
    },
  });
});
