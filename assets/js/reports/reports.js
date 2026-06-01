let activeReportType = null;

function selectReportType(card) {
  document
    .querySelectorAll(".report-type-card")
    .forEach((c) => c.classList.remove("active"));
  card.classList.add("active");
  activeReportType = card.dataset.type;
}

function generateReport(type) {
  let filterValue = null;

  if (type === "complete_plantillas") {
    filterValue = document.getElementById("selectBrandComplete").value;
    if (!filterValue) {
      Swal.fire({
        icon: "warning",
        title: "No brand selected",
        text: "Please select a brand first.",
        confirmButtonColor: "#2d68c4",
      });
      return;
    }
  } else if (type === "vacant_plantillas") {
    filterValue = document.getElementById("selectBrandVacant").value;
    if (!filterValue) {
      Swal.fire({
        icon: "warning",
        title: "No brand selected",
        text: "Please select a brand first.",
        confirmButtonColor: "#2d68c4",
      });
      return;
    }
  } else if (type === "employee_report") {
    filterValue = document.getElementById("selectBranch").value;
    if (!filterValue) {
      Swal.fire({
        icon: "warning",
        title: "No branch selected",
        text: "Please select a branch first.",
        confirmButtonColor: "#2d68c4",
      });
      return;
    }
  }

  bootstrap.Modal.getInstance(document.querySelector(".modal.show"))?.hide();

  console.log("Generate:", type, "| Filter:", filterValue);

  // TODO: loadReportTable(type, filterValue);
}
