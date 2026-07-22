$(document).ready(function () {
  // =========================
  // SUFFIX DATATABLE
  // =========================
  window.suffixTable = $("#suffixTable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    ordering: false,
    responsive: true,
    dom: "lrtip",

    ajax: {
      url: "functions/fetch_suffix.php",
      type: "POST",
      data: function (d) {
        d.name = $("#filterSuffix").val();
      },
    },

    columns: [
      { data: "suffix" },

      // ACTIONS
      {
        data: null,
        orderable: false,
        className: "text-center",
        render: function (data, type, row) {
          return `
            <div class="action-btns">
              <button class="btn btn-outline-primary btn-sm edit-suffix-btn"
                      data-id="${row.id}"
                      data-suffix="${row.suffix}">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger btn-sm delete-suffix-btn"
                      data-id="${row.id}"
                      data-suffix="${row.suffix}">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          `;
        },
      },
    ],
  });

  $("#filterSuffix").on("input", function () {
    suffixTable.draw();
  });

  // =========================
  // CATEGORIES DATATABLE
  // =========================
  window.categoriesTable = $("#categoriesTable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    ordering: false,
    responsive: true,
    dom: "lrtip",

    ajax: {
      url: "functions/fetch_categories.php",
      type: "POST",
      data: function (d) {
        d.name = $("#filterCategory").val();
      },
    },

    columns: [
      { data: "categories" },

      // ACTIONS
      {
        data: null,
        orderable: false,
        className: "text-center",
        render: function (data, type, row) {
          return `
            <div class="action-btns">
              <button class="btn btn-outline-primary btn-sm edit-category-btn"
                      data-id="${row.id}"
                      data-category="${row.categories}">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger btn-sm delete-category-btn"
                      data-id="${row.id}"
                      data-category="${row.categories}">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          `;
        },
      },
    ],
  });

  $("#filterCategory").on("input", function () {
    categoriesTable.draw();
  });

  // =========================
  // RESET ON MODAL CLOSE
  // =========================
  $("#suffixModal").on("hidden.bs.modal", function () {
    $("#suffixId").val("");
    $("#suffixName").val("");
  });

  $("#categoryModal").on("hidden.bs.modal", function () {
    $("#categoryId").val("");
    $("#categoryName").val("");
  });

  // =========================
  // FORCE UPPERCASE
  // =========================
  $(document).on("input", "#suffixName, #categoryName", function () {
    this.value = this.value.toUpperCase();
  });

  // =========================
  // OPEN EDIT MODAL (SUFFIX)
  // =========================
  $(document).on("click", ".edit-suffix-btn", function () {
    const id = $(this).data("id");
    const suffix = $(this).data("suffix");

    $("#suffixId").val(id);
    $("#suffixName").val(suffix);

    $("#suffixModal").modal("show");
  });

  // =========================
  // OPEN EDIT MODAL (CATEGORY)
  // =========================
  $(document).on("click", ".edit-category-btn", function () {
    const id = $(this).data("id");
    const category = $(this).data("category");

    $("#categoryId").val(id);
    $("#categoryName").val(category);

    $("#categoryModal").modal("show");
  });

  // =========================
  // SAVE SUFFIX
  // =========================
  $("#suffixForm").on("submit", function (e) {
    e.preventDefault();

    const id = $("#suffixId").val();
    const suffix = $("#suffixName").val()?.trim().toUpperCase() || "";
    const isEdit = id && id !== "";

    if (!suffix) {
      Swal.fire({
        icon: "warning",
        title: "Required",
        text: "Suffix is required.",
      });
      return;
    }

    Swal.fire({
      title: isEdit ? "Update this suffix?" : "Add this suffix?",
      text: suffix,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#2d68c4",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes",
    }).then((result) => {
      if (!result.isConfirmed) return;
      submitSuffix(id, suffix, isEdit);
    });
  });

  function submitSuffix(id, suffix, isEdit) {
    $.ajax({
      url: "functions/save_suffix.php",
      type: "POST",
      dataType: "json",
      data: { id, suffix },

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

          $("#suffixModal").modal("hide");
          window.suffixTable.ajax.reload(null, false);
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

  // =========================
  // SAVE CATEGORY
  // =========================
  $("#categoryForm").on("submit", function (e) {
    e.preventDefault();

    const id = $("#categoryId").val();
    const category = $("#categoryName").val()?.trim().toUpperCase() || "";
    const isEdit = id && id !== "";

    if (!category) {
      Swal.fire({
        icon: "warning",
        title: "Required",
        text: "Category is required.",
      });
      return;
    }

    Swal.fire({
      title: isEdit ? "Update this category?" : "Add this category?",
      text: category,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#2d68c4",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes",
    }).then((result) => {
      if (!result.isConfirmed) return;
      submitCategory(id, category, isEdit);
    });
  });

  function submitCategory(id, category, isEdit) {
    $.ajax({
      url: "functions/save_category.php",
      type: "POST",
      dataType: "json",
      data: { id, category },

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

          $("#categoryModal").modal("hide");
          window.categoriesTable.ajax.reload(null, false);
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

  // =========================
  // DELETE SUFFIX
  // =========================
  $(document).on("click", ".delete-suffix-btn", function () {
    const id = $(this).data("id");
    const suffix = $(this).data("suffix");

    Swal.fire({
      title: "Delete this suffix?",
      text: suffix,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes, delete",
    }).then((result) => {
      if (!result.isConfirmed) return;

      $.ajax({
        url: "functions/delete_suffix.php",
        type: "POST",
        dataType: "json",
        data: { id },

        success: function (res) {
          if (res.success) {
            Swal.fire({
              icon: "success",
              title: "Deleted",
              timer: 1200,
              showConfirmButton: false,
            });
            window.suffixTable.ajax.reload(null, false);
          } else {
            Swal.fire({
              icon: "error",
              title: "Error",
              text: res.message || "Delete failed.",
            });
          }
        },

        error: function () {
          Swal.fire({ icon: "error", title: "Server Error" });
        },
      });
    });
  });

  // =========================
  // DELETE CATEGORY
  // =========================
  $(document).on("click", ".delete-category-btn", function () {
    const id = $(this).data("id");
    const category = $(this).data("category");

    Swal.fire({
      title: "Delete this category?",
      text: category,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Yes, delete",
    }).then((result) => {
      if (!result.isConfirmed) return;

      $.ajax({
        url: "functions/delete_category.php",
        type: "POST",
        dataType: "json",
        data: { id },

        success: function (res) {
          if (res.success) {
            Swal.fire({
              icon: "success",
              title: "Deleted",
              timer: 1200,
              showConfirmButton: false,
            });
            window.categoriesTable.ajax.reload(null, false);
          } else {
            Swal.fire({
              icon: "error",
              title: "Error",
              text: res.message || "Delete failed.",
            });
          }
        },

        error: function () {
          Swal.fire({ icon: "error", title: "Server Error" });
        },
      });
    });
  });
});
