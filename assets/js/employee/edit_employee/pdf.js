document.addEventListener("DOMContentLoaded", () => {
  const openPrintModalBtn = document.getElementById("openPrintModalBtn");
  const printPdfModal = new bootstrap.Modal(
    document.getElementById("printPdfModal"),
  );

  openPrintModalBtn.addEventListener("click", () => {
    printPdfModal.show();
  });

  document.getElementById("generatePdfBtn").addEventListener("click", () => {
    const recipientName = document.getElementById("recipientName").value.trim();
    const recipientPosition = document
      .getElementById("recipientPosition")
      .value.trim();

    if (!recipientName || !recipientPosition) {
      Swal.fire({
        icon: "warning",
        title: "Missing Required Fields",
        text: "Please fill in Recipient Name and Position before generating PDF.",
      });
      return;
    }

    const payload = {
      employee_id: document.getElementById("editPromodizerId").value,

      recipient_name: recipientName,
      recipient_position: recipientPosition,
      end_date: document.getElementById("recipientEndDate").value,

      first_name: document.getElementById("editFirstName").value,
      middle_name: document.getElementById("editMiddleName").value,
      last_name: document.getElementById("editLastName").value,
      suffix: document.getElementById("editSuffix").value,

      branch: document.getElementById("editBranch").value,
      brand: document.getElementById("editBrand").value,
      employment_status: document.getElementById("editEmploymentStatus").value,
      sub_status: document.getElementById("editSubStatus").value,
      status: document.getElementById("editStatus").value,
      remarks: document.getElementById("editRemarks").value,
      effectivity_date: document.getElementById("editDateHired").value,
    };

    fetch("functions/generate_letter_pdf.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    })
      .then(async (response) => {
        console.log("Status:", response.status);
        return response.blob();
      })
      .then((blob) => {
        const url = window.URL.createObjectURL(blob);

        const a = document.createElement("a");
        a.href = url;
        a.target = "_blank";
        a.click();
      })
      .catch((error) => {
        console.error(error);
        Swal.fire("Error", "Failed to generate PDF", "error");
      });
  });
});
