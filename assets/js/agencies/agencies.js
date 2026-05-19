$(document).ready(function () {
  // =========================
  // DATATABLE (INIT ONCE ONLY)
  // =========================
  const table = $("#agencyTable").DataTable({
    pageLength: 25,
    ordering: false,
    responsive: true,
    dom: "lrtip",
  });

  // =========================
  // OPEN ADD MODAL
  // =========================
  $(document).on("click", ".addAgencyBtn", function () {
    $("#agencyId").val("");
    $("#agencyName").val("");

    $(".modal-title").text("Add Agency");

    $("#agencyModal").modal("show");
  });

  $(document).on("input", "#agencyName", function () {
    this.value = this.value.toUpperCase();
  });

  // =========================
  // EDIT
  // =========================
  $(document).on("click", ".editAgencyBtn", function () {
    $("#agencyId").val($(this).data("id"));
    $("#agencyName").val($(this).data("name"));

    $(".modal-title").text("Edit Agency");

    $("#agencyModal").modal("show");
  });

  // =========================
  // SAVE (ADD / EDIT)
  // =========================
  $("#agencyForm").on("submit", function (e) {
    e.preventDefault();

    const id = $("#agencyId").val();
    const agency = $("#agencyName").val().trim().toUpperCase();

    if (agency === "") {
      Swal.fire({
        icon: "warning",
        title: "Required",
        text: "Agency name is required.",
      });
      return;
    }

    const isEdit = id !== "";

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

      submitAgency(id, agency, isEdit);
    });
  });

  // =========================
  // ACTUAL AJAX
  // =========================
  function submitAgency(id, agency, isEdit) {
    $.ajax({
      url: "functions/save_agency.php",
      type: "POST",
      dataType: "json",
      data: {
        id: id,
        agency: agency,
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
            text: isEdit
              ? "Agency updated successfully."
              : "Agency added successfully.",
            timer: 1500,
            showConfirmButton: false,
          });

          $("#agencyModal").modal("hide");

          setTimeout(() => location.reload(), 800);
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
