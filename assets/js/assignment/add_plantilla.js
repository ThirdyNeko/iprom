// =========================
// SHARED FETCH ERROR HELPERS
// =========================

// Pulls the most useful message out of a failed fetch Response.
async function getFetchErrorMessage(res) {
  // Try JSON body first (e.g. { success:false, message:"..." })
  try {
    const data = await res.clone().json();
    if (data && data.message) return data.message;
  } catch (e) {
    // not JSON, fall through
  }

  // Fall back to raw text (PHP warning/HTML error page), stripped & trimmed
  try {
    const text = await res.text();
    const stripped = text.replace(/<[^>]*>/g, "").trim();
    if (stripped) return stripped.substring(0, 500);
  } catch (e) {
    // ignore
  }

  return `Something went wrong (HTTP ${res.status}).`;
}

const branchSelect = document.getElementById("branchSelect");
const brandSelect = document.getElementById("brandSelect");
const form = document.getElementById("addPlantillaForm");

branchSelect.addEventListener("change", async () => {
  const branch = branchSelect.value.trim();
  brandSelect.innerHTML = '<option value="">Loading...</option>';

  if (!branch) {
    brandSelect.innerHTML = '<option value="">Select Brand</option>';
    return;
  }

  try {
    // Fetch unused brands for selected branch using the SP
    const res = await fetch(
      `functions/get_available_brands.php?branch=${encodeURIComponent(branch)}`,
    );

    if (!res.ok) {
      throw new Error(await getFetchErrorMessage(res));
    }

    const data = await res.json();

    brandSelect.innerHTML = '<option value="">Select Brand</option>';
    if (data.length === 0) {
      brandSelect.innerHTML = '<option value="">No available brands</option>';
      return;
    }

    data.forEach((brand) => {
      const option = document.createElement("option");
      option.value = brand;
      option.textContent = brand;
      brandSelect.appendChild(option);
    });
  } catch (err) {
    console.error(err);
    // Option text is set via textContent below, so raw error text is safe here.
    const errOption = document.createElement("option");
    errOption.value = "";
    errOption.textContent = err.message || "Error loading brands";
    brandSelect.innerHTML = "";
    brandSelect.appendChild(errOption);
  }
});

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;

  const formData = new FormData(form);
  const branch = branchSelect.value.trim();
  const brand = brandSelect.value.trim();

  if (!branch || !brand) {
    Swal.fire({
      icon: "warning",
      title: "Required Fields",
      text: "Please select both Branch and Brand.",
    });
    btn.disabled = false;
    return;
  }

  try {
    // Confirm action
    const confirm = await Swal.fire({
      icon: "question",
      title: "Add Plantilla?",
      text: `Branch: ${branch}\nBrand: ${brand}`,
      showCancelButton: true,
      confirmButtonText: "Yes",
      cancelButtonText: "Cancel",
      confirmButtonColor: "#28a745",
    });
    if (!confirm.isConfirmed) return (btn.disabled = false);

    // Submit plantilla
    const submitRes = await fetch("functions/add_plantilla.php", {
      method: "POST",
      body: formData,
    });

    if (!submitRes.ok) {
      throw new Error(await getFetchErrorMessage(submitRes));
    }

    const submitData = await submitRes.json();

    if (submitData.success) {
      Swal.fire({
        icon: "success",
        title: "Plantilla has been successfully added",
      }).then(() => {
        form.reset();
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("addPlantillaModal"),
        );
        modal.hide();
        window.assignmentTable.ajax.reload();
      });
    } else {
      Swal.fire({
        icon: "error",
        title: "Error!",
        text: submitData.message || "Failed to add plantilla.",
      });
    }
  } catch (err) {
    console.error(err);
    Swal.fire({
      icon: "error",
      title: "Unexpected Error",
      text: err.message || "An unexpected error occurred. Please try again.",
    });
  } finally {
    btn.disabled = false;
  }
});
