function parseSqlDate(value) {
  if (!value) return "";
  // Normalize SQL Server's space-separated datetime for cross-browser parsing
  const normalized = value.replace(" ", "T");
  const d = new Date(normalized);
  if (isNaN(d)) return value;
  return d.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function fillOrDash(value) {
  return value === null || value === undefined || value === "" ? "—" : value;
}

$(document).on(
  "click",
  "#BlacklistedtablePromodiser tbody tr, #BlacklistedtableDirectHire tbody tr",
  function () {
    const id = $(this).data("id");
    if (!id) return;

    $.ajax({
      url: "functions/get_blacklisted_details.php",
      type: "POST",
      dataType: "json",
      data: { id: id },
    })
      .done(function (res) {
        if (!res.success) {
          Swal.fire("Error", res.message || "Unable to load record.", "error");
          return;
        }

        const d = res.data;
        const isDirectHire = d.brand === "DIRECT HIRE";

        $("#vb_first_name").val(fillOrDash(d.first_name));
        $("#vb_middle_name").val(fillOrDash(d.middle_name));
        $("#vb_last_name").val(fillOrDash(d.last_name));
        $("#vb_suffix").val(fillOrDash(d.suffix));
        $("#vb_gender").val(fillOrDash(d.gender));
        $("#vb_birthday").val(d.birthday ? parseSqlDate(d.birthday) : "—");
        $("#vb_marital_status").val(fillOrDash(d.marital_status));
        $("#vb_branch").val(fillOrDash(d.branch));
        $("#vb_brand").val(fillOrDash(d.brand));
        $("#vb_region").val(fillOrDash(d.region));
        $("#vb_employment_status").val(fillOrDash(d.employment_status));
        $("#vb_end_date").val(d.end_date ? parseSqlDate(d.end_date) : "—");
        $("#vb_remarks").val(fillOrDash(d.remarks));

        $("#vb_employment_status_group").toggle(!isDirectHire);
        $("#vb_brand_group").toggle(!isDirectHire);

        $("#viewBlacklistedModal").modal("show");
      })
      .fail(function () {
        Swal.fire("Error", "Something went wrong loading the record.", "error");
      });
  },
);
