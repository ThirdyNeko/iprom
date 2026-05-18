$(document).ready(function () {
  let table = $("#Branchtable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    ordering: false,

    ajax: {
      url: "functions/fetch_branches.php",
      type: "POST",
    },

    columns: [
      { data: "branch" },
      { data: "corpo" },
      { data: "region" },
      { data: "area" },
      { data: "status" },
    ],
  });

  $(document).on("click", "#syncBranchesBtn", function () {
    const btn = $(this);

    Swal.fire({
      title: "Sync Branches?",
      text: "This will sync branches from the source system.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, Sync",
      cancelButtonText: "Cancel",
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
    }).then((result) => {
      if (!result.isConfirmed) return;

      btn.prop("disabled", true);

      // SHOW LOADING SCREEN
      Swal.fire({
        title: "Syncing Branches...",
        html: "Please wait while branch data is updating.",
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      $.ajax({
        url: "functions/sync_branches.php",
        type: "POST",
        dataType: "json",

        success: function (res) {
          if (res.success) {
            // WAIT FOR DATATABLE TO FINISH RELOADING
            table.ajax.reload(function () {
              Swal.fire({
                title: "Success!",
                text: res.message,
                icon: "success",
                timer: 2000,
                showConfirmButton: false,
              });
            }, false);
          } else {
            Swal.fire({
              title: "Sync Failed",
              text: res.message || "Something went wrong.",
              icon: "error",
            });
          }
        },

        error: function (xhr) {
          console.log(xhr.responseText);

          Swal.fire({
            title: "Server Error",
            text: "Sync failed. Check console/network.",
            icon: "error",
          });
        },

        complete: function () {
          btn.prop("disabled", false).html("⟳ Sync Branches");
        },
      });
    });
  });
});
