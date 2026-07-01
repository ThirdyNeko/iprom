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
      { data: "birthday", name: "birthday" },
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
