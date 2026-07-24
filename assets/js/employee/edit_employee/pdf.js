document.addEventListener("DOMContentLoaded", () => {
  const openPrintModalBtn = document.getElementById("openPrintModalBtn");
  const printPdfModal = new bootstrap.Modal(
    document.getElementById("printPdfModal"),
  );

  const singleRecipientFields = document.getElementById(
    "singleRecipientFields",
  );
  const multiRecipientFields = document.getElementById("multiRecipientFields");

  function val(id) {
    const el = document.getElementById(id);
    if (!el) {
      console.error(
        `pdf.js: expected element #${id} was not found in the DOM.`,
      );
      return "";
    }
    return el.value;
  }

  function isMultiBranch() {
    const subStatus = val("editSubStatus");
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

  // ============================================================
  // FORCE UPPERCASE ON THE ACTUAL VALUE (not just CSS display)
  // CSS text-transform only changes what's rendered on screen —
  // the underlying .value stays whatever case was typed. Since
  // that .value is what gets sent to the search endpoint and
  // (eventually) the PDF, we uppercase it in real time here.
  // Preserves cursor position so typing doesn't jump around.
  // ============================================================
  function forceUppercase(input) {
    input.addEventListener("input", () => {
      const cursorPos = input.selectionStart;
      input.value = input.value.toUpperCase();
      input.setSelectionRange(cursorPos, cursorPos);
    });
  }

  // ============================================================
  // BRANCH MANAGER SEARCH
  // Looks up role=branch_manager users scoped to a specific
  // branch code, matching the typed full name against
  // "first_name last_name [suffix]" server-side.
  // ============================================================
  async function searchBranchManager(branchCode, fullName) {
    const response = await fetch("functions/search_branch_manager.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        branch_code: branchCode,
        full_name: fullName,
      }),
    });

    if (!response.ok) {
      throw new Error("Search request failed");
    }

    return response.json();
  }

  // Wires up search behavior for a single recipient "unit" — works
  // for both the single-recipient fields and each multi-recipient
  // block, since they share the same class-based structure.
  //
  // container: element holding the name/position/status/search-button
  // getBranchCode: function returning the branch code to scope the search to
  function wireRecipientSearch(container, getBranchCode) {
    const nameInput = container.querySelector(".recipient-search-name, #recipientName");
    const positionInput = container.querySelector(".recipient-position, #recipientPosition");
    const searchBtn = container.querySelector(".recipient-search-btn, #searchRecipientBtn");
    const statusEl = container.querySelector(".recipient-search-status, #recipientSearchStatus");

    if (!nameInput || !positionInput || !searchBtn) {
      console.error("pdf.js: recipient search block is missing expected fields.", container);
      return;
    }

    // Uppercase the actual value as the user types, not just via CSS
    forceUppercase(nameInput);

    function setStatus(text, cls) {
      if (!statusEl) return;
      statusEl.textContent = text;
      statusEl.className = "form-text recipient-search-status " + (cls || "");
    }

    function invalidate() {
      container.dataset.verified = "false";
      positionInput.value = "";
      setStatus("", "");
    }

    // Any edit to the name invalidates a previous match — forces a
    // fresh search before the recipient can be used to generate.
    nameInput.addEventListener("input", invalidate);

    container.dataset.verified = "false";

    searchBtn.addEventListener("click", async () => {
      const branchCode = getBranchCode();
      const fullName = nameInput.value.trim();

      if (!branchCode) {
        setStatus("No branch selected for this recipient.", "text-danger");
        return;
      }
      if (!fullName) {
        setStatus("Enter a name first.", "text-danger");
        return;
      }

      searchBtn.disabled = true;
      const originalBtnHtml = searchBtn.innerHTML;
      searchBtn.innerHTML = "Searching...";
      setStatus("", "");

      try {
        const result = await searchBranchManager(branchCode, fullName);

        if (!result.success) {
          setStatus(result.message || "Search failed.", "text-danger");
          container.dataset.verified = "false";
          positionInput.value = "";
        } else if (result.found) {
          positionInput.value = (result.position || "").toUpperCase();
          container.dataset.verified = "true";
          setStatus("✓ Branch manager found.", "text-success");
        } else {
          container.dataset.verified = "false";
          positionInput.value = "";
          setStatus(result.message || "No matching branch manager found.", "text-danger");
        }
      } catch (err) {
        console.error(err);
        container.dataset.verified = "false";
        positionInput.value = "";
        setStatus("Search failed. Please try again.", "text-danger");
      } finally {
        searchBtn.disabled = false;
        searchBtn.innerHTML = originalBtnHtml;
      }
    });
  }

  function buildMultiRecipientFields(branches) {
    multiRecipientFields.innerHTML = "";

    branches.forEach((branch) => {
      const block = document.createElement("div");
      block.className = "border rounded p-3 mb-3";
      block.dataset.branchCode = branch.code;
      block.dataset.verified = "false";

      block.innerHTML = `
        <div class="form-check mb-2">
          <input type="checkbox" class="form-check-input recipient-include-multi" checked>
          <label class="form-check-label fw-bold recipient-branch-label">${branch.name}</label>
        </div>
        <div class="mb-2">
          <label class="form-label">Recipient Full Name</label>
          <div class="input-group">
            <input type="text" class="form-control recipient-name-multi recipient-search-name">
            <button type="button" class="btn btn-outline-danger recipient-search-btn">
              <i class="bi bi-search"></i> Search
            </button>
          </div>
          <div class="form-text recipient-search-status"></div>
        </div>
        <div class="mb-2">
          <label class="form-label">Position</label>
          <input type="text" class="form-control recipient-position-multi recipient-position" readonly placeholder="Auto-filled after search">
        </div>
      `;

      multiRecipientFields.appendChild(block);

      const checkbox = block.querySelector(".recipient-include-multi");
      const nameInput = block.querySelector(".recipient-search-name");
      const positionInput = block.querySelector(".recipient-position");
      const searchBtn = block.querySelector(".recipient-search-btn");

      // Uppercase the actual value as the user types
      forceUppercase(nameInput);

      function syncBlockState() {
        const included = checkbox.checked;
        [nameInput, positionInput, searchBtn].forEach((el) => {
          if (el) el.disabled = !included;
        });
        block.classList.toggle("opacity-50", !included);
      }

      checkbox.addEventListener("change", syncBlockState);
      syncBlockState();

      // Each block is scoped to its own branch code.
      wireRecipientSearch(block, () => block.dataset.branchCode);
    });
  }

  // Wire the single-recipient fields once at load. It's scoped to
  // whatever the currently selected main branch is at search time.
  wireRecipientSearch(singleRecipientFields, () => val("editBranch"));

  // =========================
  // OPEN MODAL
  // =========================
  openPrintModalBtn.addEventListener("click", () => {
    const startDate = val("editStartDate");
    const effectivityDate = startDate || val("editDateHired");

    const loaDateHired = document.getElementById("loaDateHired");
    if (loaDateHired) loaDateHired.value = effectivityDate;

    const loaLabel = document.querySelector("label[for='loaDateHired']");
    if (loaLabel) {
      loaLabel.textContent = startDate ? "Effectivity Date" : "Date Hired";
    }

    const dateHired = val("editStartDate") || val("editDateHired");
    if (loaDateHired) loaDateHired.value = dateHired;

    if (dateHired) {
      const hiredDate = new Date(dateHired);

      const defaultDate = new Date(hiredDate);
      defaultDate.setMonth(defaultDate.getMonth() + 3);

      const maxDate = new Date(hiredDate);
      maxDate.setMonth(maxDate.getMonth() + 6);

      const formatDate = (d) => d.toISOString().split("T")[0];

      const endInput = document.getElementById("recipientEndDate");
      const existingEndDate = document.getElementById("editEndDate")?.value;

      if (endInput) {
        endInput.value = existingEndDate || formatDate(defaultDate);
        endInput.min = formatDate(hiredDate);
        endInput.max = formatDate(maxDate);
      }
    }

    // Reset single-recipient search state on every open, since the
    // branch (and therefore valid managers) may have changed.
    singleRecipientFields.dataset.verified = "false";
    const singleStatus = document.getElementById("recipientSearchStatus");
    if (singleStatus) {
      singleStatus.textContent = "";
      singleStatus.className = "form-text recipient-search-status";
    }
    const singlePosition = document.getElementById("recipientPosition");
    if (singlePosition) singlePosition.value = "";

    // Toggle single vs. multi recipient UI based on sub_status
    if (isMultiBranch()) {
      buildMultiRecipientFields(getBranchList());
      singleRecipientFields?.classList.add("d-none");
      multiRecipientFields?.classList.remove("d-none");
    } else {
      if (multiRecipientFields) multiRecipientFields.innerHTML = "";
      multiRecipientFields?.classList.add("d-none");
      singleRecipientFields?.classList.remove("d-none");
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

      let recipients = [];

      if (isMultiBranch()) {
        const allBlocks =
          multiRecipientFields.querySelectorAll("[data-branch-code]");

        const blocks = Array.from(allBlocks).filter((block) => {
          const cb = block.querySelector(".recipient-include-multi");
          return cb ? cb.checked : true;
        });

        if (blocks.length === 0) {
          Swal.fire({
            icon: "warning",
            title: "No Branches Selected",
            text: "Please select at least one branch to generate an LOA for.",
          });
          return;
        }

        for (const block of blocks) {
          const labelEl = block.querySelector(".recipient-branch-label");
          const nameInput = block.querySelector(".recipient-name-multi");
          const positionInput = block.querySelector(
            ".recipient-position-multi",
          );

          if (!labelEl || !nameInput || !positionInput) {
            console.error(
              "pdf.js: a multi-recipient block is missing expected fields.",
              block,
            );
            Swal.fire({
              icon: "error",
              title: "Error",
              text: "Something went wrong building the branch fields. Please close and reopen the Print LOA modal.",
            });
            return;
          }

          const branchName = labelEl.textContent;
          const recipientName = nameInput.value.trim().toUpperCase();
          const recipientPosition = positionInput.value.trim().toUpperCase();

          if (!recipientName) {
            Swal.fire({
              icon: "warning",
              title: "Missing Required Fields",
              text: `Please enter the recipient's full name for ${branchName}.`,
            });
            return;
          }

          if (block.dataset.verified !== "true" || !recipientPosition) {
            Swal.fire({
              icon: "warning",
              title: "Branch Manager Not Verified",
              text: `Please click Search and confirm a matching branch manager for ${branchName} before generating.`,
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
        const nameInput = document.getElementById("recipientName");
        const positionInput = document.getElementById("recipientPosition");

        if (!nameInput || !positionInput) {
          console.error(
            "pdf.js: #recipientName or #recipientPosition not found in the DOM.",
          );
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "Something went wrong loading the print form. Please refresh and try again.",
          });
          return;
        }

        const recipientName = nameInput.value.trim().toUpperCase();
        const recipientPosition = positionInput.value.trim().toUpperCase();

        if (!recipientName) {
          Swal.fire({
            icon: "warning",
            title: "Missing Required Fields",
            text: "Please enter the recipient's full name before generating PDF.",
          });
          return;
        }

        if (singleRecipientFields.dataset.verified !== "true" || !recipientPosition) {
          Swal.fire({
            icon: "warning",
            title: "Branch Manager Not Verified",
            text: "Please click Search and confirm a matching branch manager before generating.",
          });
          return;
        }

        const branchSelect = document.getElementById("editBranch");
        recipients.push({
          branch_code: branchSelect ? branchSelect.value : "",
          branch_name:
            branchSelect?.options[branchSelect.selectedIndex]?.text || "",
          recipient_name: recipientName,
          recipient_position: recipientPosition,
        });
      }

      const basePayload = {
        employee_id: employeeId,
        id: val("editPromodizerId"),
        loa_code: val("editLoaCode"),

        end_date: val("recipientEndDate"),

        first_name: val("editFirstName"),
        middle_name: val("editMiddleName"),
        last_name: val("editLastName"),
        suffix: val("editSuffix"),

        branch: val("editBranch"),

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

        brand: val("editBrand"),
        agency: val("editAgency"),
        employment_status: val("editEmploymentStatus"),
        sub_status: val("editSubStatus"),
        status: val("editStatus"),
        remarks: val("editRemarks"),
        effectivity_date: val("editStartDate") || val("editDateHired"),
      };

      const lastName = val("editLastName");

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