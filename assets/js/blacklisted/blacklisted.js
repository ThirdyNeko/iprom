$(function () {
  const table = $("#Blacklistedtable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    ordering: false,
    ajax: {
      url: "functions/get_blacklisted.php",
      type: "POST",
      data: function (d) {
        d.search.value = $("#filterName").val();
      },
    },
    columns: [
      { data: "full_name", name: "full_name" },
      { data: "branch", name: "branch" },
      { data: "brand", name: "brand" },
      { data: "employment_status", name: "employment_status" },
    ],
    language: {
      emptyTable: "No blacklisted records found.",
    },
  });

  let searchTimeout;
  $("#filterName").on("input", function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => table.ajax.reload(), 400);
  });
});

$("#syncBlacklistBtn").on("click", function () {
  Swal.fire({
    title: "Sync Blacklisted Records?",
    text: "This will import any employees marked BLACKLISTED / AWOL / TERMINATED that aren't already in this list.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sync",
  }).then((result) => {
    if (!result.isConfirmed) return;

    Swal.fire({
      title: "Syncing...",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    });

    $.ajax({
      url: "functions/sync_blacklisted.php",
      type: "POST",
      dataType: "json",
    })
      .done(function (res) {
        if (res.success) {
          Swal.fire({
            icon: "success",
            title: "Sync Complete",
            text: `${res.insertedCount} new record(s) added.`,
          });
          table.ajax.reload(null, false);
        } else {
          Swal.fire("Error", res.message || "Sync failed.", "error");
        }
      })
      .fail(function () {
        Swal.fire("Error", "Something went wrong during sync.", "error");
      });
  });
});
