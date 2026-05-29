document.addEventListener("DOMContentLoaded", () => {
  const openPrintModalBtn = document.getElementById("openPrintModalBtn");
  const printPdfModal = new bootstrap.Modal(
    document.getElementById("printPdfModal"),
  );

  openPrintModalBtn.addEventListener("click", () => {
    // get date hired from employee modal
    const dateHired = document.getElementById("editDateHired").value;

    // display it inside LOA modal
    document.getElementById("loaDateHired").value = dateHired;

    // auto compute +6 months
    if (dateHired) {
      const hiredDate = new Date(dateHired);

      // default = +3 months
      const defaultDate = new Date(hiredDate);
      defaultDate.setMonth(defaultDate.getMonth() + 3);

      // max = +6 months
      const maxDate = new Date(hiredDate);
      maxDate.setMonth(maxDate.getMonth() + 6);

      const formatDate = (d) => d.toISOString().split("T")[0];

      const endInput = document.getElementById("recipientEndDate");

      // set default value
      endInput.value = formatDate(defaultDate);

      // enforce limits
      endInput.min = formatDate(hiredDate); // optional (or today if needed)
      endInput.max = formatDate(maxDate);
    }

    printPdfModal.show();
  });

  document.getElementById("generatePdfBtn").addEventListener("click", () => {
    const recipientName = document
      .getElementById("recipientName")
      .value.trim()
      .toUpperCase();
    const recipientPosition = document
      .getElementById("recipientPosition")
      .value.trim()
      .toUpperCase();

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
      roving_branches: Array.from(
        document.querySelectorAll(
          "#editRovingContainer select, #editRovingContainer input",
        ),
      ).map((el) => el.value),
      multi_brands: Array.from(
        document.querySelectorAll(
          "#editMultiBrandContainer select, #editMultiBrandContainer input",
        ),
      ).map((el) => el.value),
      brand: document.getElementById("editBrand").value,
      agency: document.getElementById("editAgency").value,
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

        // 🔥 THIS is what fixes filename
        a.download =
          "LOA_" + document.getElementById("editLastName").value + ".pdf";

        document.body.appendChild(a);
        a.click();
        a.remove();

        window.URL.revokeObjectURL(url);

        // ✅ Redirect after download is triggered
        window.location.href = "promodizers.php";
      })
      .catch((error) => {
        console.error(error);
        Swal.fire("Error", "Failed to generate PDF", "error");
      });
  });
});
