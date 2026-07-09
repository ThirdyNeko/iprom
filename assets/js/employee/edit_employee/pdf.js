document.addEventListener("DOMContentLoaded", () => {
  const openPrintModalBtn = document.getElementById("openPrintModalBtn");
  const printPdfModal = new bootstrap.Modal(
    document.getElementById("printPdfModal"),
  );

  const singleRecipientFields = document.getElementById(
    "singleRecipientFields",
  );
  const multiRecipientFields = document.getElementById("multiRecipientFields");

  function isMultiBranch() {
    const subStatus = document.getElementById("editSubStatus").value;
    return subStatus === "MULTI BRANCH" || subStatus === "HYBRID";
  }

  // Main branch + every roving branch, as {code, name}
  function getBranchList() {
    const branches = [];

    const mainSelect = document.getElementById("editBranch");
    if (mainSelect && mainSelect.value) {
      const opt = mainSelect.options[mainSelect.selectedIndex];
      branches.push({
        code: mainSelect.value,
        name: opt ? opt.text : mainSelect.value,
      });
    }

    document
      .querySelectorAll(
        "#editRovingContainer select, #editRovingContainer input",
      )
      .forEach((el) => {
        if (!el.value) return;
        let name = el.value;
        if (el.tagName === "SELECT") {
          const opt = el.options[el.selectedIndex];
          name = opt ? opt.text : el.value;
        }
        branches.push({ code: el.value, name });
      });

    return branches;
  }

  function buildMultiRecipientFields(branches) {
    multiRecipientFields.innerHTML = "";

    branches.forEach((branch) => {
      const block = document.createElement("div");
      block.className = "border rounded p-3 mb-3";
      block.dataset.branchCode = branch.code;

      block.innerHTML = `
        <div class="fw-bold mb-2 recipient-branch-label">${branch.name}</div>
        <div class="mb-2">
          <label class="form-label">Recipient Full Name</label>
          <input type="text" class="form-control recipient-name-multi">
        </div>
        <div class="mb-2">
          <label class="form-label">Position</label>
          <input type="text" class="form-control recipient-position-multi">
        </div>
      `;

      multiRecipientFields.appendChild(block);
    });
  }

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

    // Toggle single vs. multi recipient UI based on sub_status
    if (isMultiBranch()) {
      buildMultiRecipientFields(getBranchList());
      singleRecipientFields.classList.add("d-none");
      multiRecipientFields.classList.remove("d-none");
    } else {
      multiRecipientFields.innerHTML = "";
      multiRecipientFields.classList.add("d-none");
      singleRecipientFields.classList.remove("d-none");
    }

    printPdfModal.show();
  });

  // =========================
  // GENERATE PDF(S) + UPDATE DB
  // =========================
  document
    .getElementById("generatePdfBtn")
    .addEventListener("click", async () => {
      const employeeId = window.currentEmployee?.employee_id;

      if (!employeeId) {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "Employee ID not found.",
        });
        return;
      }

      // Build the recipients array (1 entry for single-branch, N for multi)
      let recipients = [];

      if (isMultiBranch()) {
        const blocks =
          multiRecipientFields.querySelectorAll("[data-branch-code]");

        for (const block of blocks) {
          const branchName = block.querySelector(
            ".recipient-branch-label",
          ).textContent;
          const recipientName = block
            .querySelector(".recipient-name-multi")
            .value.trim()
            .toUpperCase();
          const recipientPosition = block
            .querySelector(".recipient-position-multi")
            .value.trim()
            .toUpperCase();

          if (!recipientName || !recipientPosition) {
            Swal.fire({
              icon: "warning",
              title: "Missing Required Fields",
              text: `Please fill in Recipient Name and Position for ${branchName}.`,
            });
            return;
          }

          recipients.push({
            branch_code: block.dataset.branchCode,
            branch_name: branchName,
            recipient_name: recipientName,
            recipient_position: recipientPosition,
          });
        }

        if (recipients.length === 0) {
          Swal.fire({
            icon: "warning",
            title: "No Branches Found",
            text: "This employee has no branches assigned for LOA generation.",
          });
          return;
        }
      } else {
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

        const branchSelect = document.getElementById("editBranch");
        recipients.push({
          branch_code: branchSelect.value,
          branch_name:
            branchSelect.options[branchSelect.selectedIndex]?.text || "",
          recipient_name: recipientName,
          recipient_position: recipientPosition,
        });
      }

      const basePayload = {
        employee_id: employeeId,
        id: document.getElementById("editPromodizerId").value,

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

      const lastName = document.getElementById("editLastName").value;

      async function finishAndRedirect() {
        const updateRes = await fetch("functions/update_print_loa.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ employee_id: employeeId }),
        });

        const updateData = await updateRes.json();
        if (!updateData.success) console.warn("print_loa update failed");

        window.location.href = "promodizers.php";
      }

      try {
        // Generate every recipient's PDF first — no downloads triggered yet.
        const generatedFiles = [];

        for (const recipient of recipients) {
          const payload = {
            ...basePayload,
            recipient_name: recipient.recipient_name,
            recipient_position: recipient.recipient_position,
            recipient_branch_name: recipient.branch_name,
            recipient_branch_code: recipient.branch_code,
          };

          const response = await fetch("functions/generate_letter_pdf.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
          });

          if (!response.ok) throw new Error("Failed to generate PDF");

          const blob = await response.blob();
          const url = window.URL.createObjectURL(blob);

          const branchSuffix =
            recipients.length > 1
              ? "_" + recipient.branch_name.replace(/[^a-z0-9]+/gi, "_")
              : "";

          generatedFiles.push({
            url,
            filename: "LOA_" + lastName + branchSuffix + ".pdf",
            branchName: recipient.branch_name,
          });
        }

        if (generatedFiles.length === 1) {
          // Only one file — safe to auto-download directly.
          const f = generatedFiles[0];
          const a = document.createElement("a");
          a.href = f.url;
          a.download = f.filename;
          document.body.appendChild(a);
          a.click();
          a.remove();
          window.URL.revokeObjectURL(f.url);

          await finishAndRedirect();
        } else {
          // Multiple files — browsers block automatic back-to-back
          // downloads, so present a real download link per branch and
          // let the user click each one individually. Each is a genuine
          // <a download> element, so no auto-download blocking, and no
          // zipping — every branch still gets its own separate PDF file.
          const linksHtml = generatedFiles
            .map(
              (f, i) => `
                <div class="mb-2">
                  <a href="${f.url}" download="${f.filename}"
                     class="btn btn-outline-danger btn-sm w-100 loa-download-link"
                     data-index="${i}">
                    Download LOA — ${f.branchName}
                  </a>
                </div>
              `,
            )
            .join("");

          await Swal.fire({
            icon: "success",
            title: "LOAs Generated",
            html: `
              <p class="mb-3">Click each button below to download the LOA for that branch.</p>
              ${linksHtml}
            `,
            confirmButtonText: "Done",
            confirmButtonColor: "#dc3545",
            allowOutsideClick: false,
          });

          generatedFiles.forEach((f) => window.URL.revokeObjectURL(f.url));

          await finishAndRedirect();
        }
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
