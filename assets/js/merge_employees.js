function initEmployeeSelect(selector) {
  $(selector).select2({
    placeholder: "Search employee...",
    width: "100%",
    minimumInputLength: 2,
    ajax: {
      url: "functions/search_employees.php",
      dataType: "json",
      delay: 250,
      data: function (params) {
        return { q: params.term };
      },
      processResults: function (data) {
        return { results: data.results };
      },
      cache: true,
    },
  });
}

function initMergedEmployeeSelect(selector) {
  $(selector).select2({
    placeholder: "Search merged employees...",
    width: "100%",
    minimumInputLength: 0,
    ajax: {
      url: "functions/search_merged_employees.php",
      dataType: "json",
      delay: 250,
      data: function (params) {
        return { q: params.term };
      },
      processResults: function (data) {
        return { results: data.results };
      },
      cache: true,
    },
  });
}

$(document).ready(function () {
  initEmployeeSelect(".employee-search");
  initMergedEmployeeSelect(".merged-employee-search");

  // ================= MERGE =================
  $("#mergeForm").on("submit", function (e) {
    e.preventDefault();

    let data = {
      primary_employee: $('select[name="primary_employee"]').val(),
      secondary_employee: $('select[name="secondary_employee"]').val(),
    };

    $.ajax({
      url: "functions/merge.php",
      type: "POST",
      data: data,
      success: function (res) {
        Swal.fire({
          icon: "success",
          title: "Merge Completed",
          text: res,
        });

        $("#mergeForm")[0].reset();
        $(".employee-search").val(null).trigger("change");
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "Merge Failed",
          text: "Something went wrong while merging employees.",
        });
      },
    });
  });

  // ================= UNMERGE =================
  $("#unmergeForm").on("submit", function (e) {
    e.preventDefault();

    let employee_id = $('select[name="employee_id"]').val();

    if (!employee_id) {
      Swal.fire({
        icon: "warning",
        title: "No Employee Selected",
        text: "Please select a merged employee to unmerge.",
      });
      return;
    }

    $.ajax({
      url: "functions/unmerge.php",
      type: "POST",
      data: {
        employee_id: employee_id,
      },
      success: function (res) {
        Swal.fire({
          icon: "success",
          title: "Unmerge Completed",
          text: res,
        });

        $("#unmergeForm")[0].reset();
        $(".merged-employee-search").val(null).trigger("change");
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "Unmerge Failed",
          text: "Something went wrong while unmerging employees.",
        });
      },
    });
  });
});

// ================= PREVENT SAME EMPLOYEE =================
$(document).on("change", ".employee-search", function () {
  let primary = $('select[name="primary_employee"]').val();
  let secondary = $('select[name="secondary_employee"]').val();

  if (primary && secondary && primary === secondary) {
    Swal.fire({
      icon: "warning",
      title: "Invalid Selection",
      text: "You cannot select the same employee.",
    });

    $(this).val(null).trigger("change");
  }
});

// ================= CONFIRMATIONS (SWAL REPLACEMENT) =================
function confirmMerge() {
  return Swal.fire({
    title: "Confirm Merge?",
    text: "This will transfer all history to the selected employee.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Yes, Merge",
    cancelButtonText: "Cancel",
  }).then((result) => {
    return result.isConfirmed;
  });
}

function confirmUnmerge() {
  return Swal.fire({
    title: "Confirm Unmerge?",
    text: "This will restore previous employee history.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Yes, Unmerge",
    cancelButtonText: "Cancel",
  }).then((result) => {
    return result.isConfirmed;
  });
}
