document.addEventListener("DOMContentLoaded", () => {
  const openPrintModalBtn = document.getElementById("openPrintModalBtn");
  const printPdfModal = new bootstrap.Modal(
    document.getElementById("printPdfModal"),
  );

  // =========================
  // OPEN MODAL
  // =========================
  openPrintModalBtn.addEventListener("click", () => {
    const startDate = document.getElementById("editStartDate").value;
    const effectivityDate =
      startDate || document.getElementById("editDateHired").value;

    document.getElementById("loaDateHired").value = effectivityDate;
    document.querySelector("label[for='loaDateHired']").textContent = startDate
      ? "Effectivity Date"
      : "Date Hired";
    const dateHired =
      document.getElementById("editStartDate").value ||
      document.getElementById("editDateHired").value;

    document.getElementById("loaDateHired").value = dateHired;

    if (dateHired) {
      const hiredDate = new Date(dateHired);

      const defaultDate = new Date(hiredDate);
      defaultDate.setMonth(defaultDate.getMonth() + 3);

      const maxDate = new Date(hiredDate);
      maxDate.setMonth(maxDate.getMonth() + 6);

      const formatDate = (d) => d.toISOString().split("T")[0];

      const endInput = document.getElementById("recipientEndDate");
      const existingEndDate = document.getElementById("editEndDate")?.value;

      endInput.value = existingEndDate || formatDate(defaultDate);
      endInput.min = formatDate(hiredDate);
      endInput.max = formatDate(maxDate);
    }

    printPdfModal.show();
  });

  // =========================
  // GENERATE PDF + UPDATE DB
  // =========================
  document
    .getElementById("generatePdfBtn")
    .addEventListener("click", async () => {
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

      // ✅ FIX: always use global employee object
      const employeeId = window.currentEmployee?.employee_id;

      if (!employeeId) {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "Employee ID not found.",
        });
        return;
      }

      const payload = {
        employee_id: employeeId,
        id: document.getElementById("editPromodizerId").value,

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
        employment_status: document.getElementById("editEmploymentStatus")
          .value,
        sub_status: document.getElementById("editSubStatus").value,
        status: document.getElementById("editStatus").value,
        remarks: document.getElementById("editRemarks").value,
        effectivity_date:
          document.getElementById("editStartDate").value ||
          document.getElementById("editDateHired").value,
      };

      try {
        // =========================
        // 1. GENERATE PDF
        // =========================
        const response = await fetch("functions/generate_letter_pdf.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
        });

        if (!response.ok) {
          throw new Error("Failed to generate PDF");
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);

        const a = document.createElement("a");
        a.href = url;
        a.download =
          "LOA_" + document.getElementById("editLastName").value + ".pdf";

        document.body.appendChild(a);
        a.click();
        a.remove();

        window.URL.revokeObjectURL(url);

        // =========================
        // 2. UPDATE print_loa = 0 (SQL)
        // =========================
        const updateRes = await fetch("functions/update_print_loa.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            employee_id: employeeId,
          }),
        });

        const updateData = await updateRes.json();

        if (!updateData.success) {
          console.warn("print_loa update failed");
        }

        // =========================
        // 3. REDIRECT
        // =========================
        window.location.href = "promodizers.php";
      } catch (error) {
        console.error(error);

        Swal.fire({
          icon: "error",
          title: "Error",
          text: "Failed to generate PDF",
        });
      }
    });
});
