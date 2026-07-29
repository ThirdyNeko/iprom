document.addEventListener("DOMContentLoaded", function () {
  const modalEl = document.getElementById("changePictureModal");
  if (!modalEl) return;

  const changePictureBtn = document.getElementById("changeEmployeePictureBtn");
  const fileInput = document.getElementById("cpPictureInput");
  const errorBox = document.getElementById("cpPictureError");
  const cropWrap = document.getElementById("cpCropWrap");
  const cropImage = document.getElementById("cpCropImage");
  const zoomInBtn = document.getElementById("cpZoomInBtn");
  const zoomOutBtn = document.getElementById("cpZoomOutBtn");
  const resetBtn = document.getElementById("cpResetBtn");
  const nextBtn = document.getElementById("cpNextBtn");
  const backBtn = document.getElementById("cpBackBtn");
  const saveBtn = document.getElementById("cpSaveBtn");
  const step1 = document.getElementById("cpStep1");
  const step2 = document.getElementById("cpStep2");
  const stepItems = modalEl.querySelectorAll(".step-item");
  const confirmImg = document.getElementById("cpConfirmPicture");
  const loadingOverlay = document.getElementById("cpLoadingOverlay");

  let cropper = null;
  let croppedBlob = null;
  let croppedMime = "image/jpeg";
  let bsModal = null;

  function showStep(n) {
    [step1, step2].forEach((el, i) =>
      el.classList.toggle("d-none", i + 1 !== n),
    );
    stepItems.forEach((el) =>
      el.classList.toggle("active", Number(el.dataset.cpStep) === n),
    );
    backBtn.classList.toggle("d-none", n === 1);
    nextBtn.classList.toggle("d-none", n !== 1);
    saveBtn.classList.toggle("d-none", n !== 2);
  }

  function resetModal() {
    fileInput.value = "";
    errorBox.classList.add("d-none");
    cropWrap.classList.add("d-none");
    nextBtn.disabled = true;
    croppedBlob = null;
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
    showStep(1);
  }

  changePictureBtn?.addEventListener("click", function () {
    resetModal();
    bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
  });

  fileInput?.addEventListener("change", function () {
    errorBox.classList.add("d-none");
    const file = fileInput.files[0];
    if (!file) return;

    const validTypes = ["image/jpeg", "image/png"];
    if (!validTypes.includes(file.type)) {
      errorBox.textContent = "Please upload a JPG or PNG file.";
      errorBox.classList.remove("d-none");
      fileInput.value = "";
      return;
    }

    const maxSizeBytes = 8 * 1024 * 1024; // 8MB
    if (file.size > maxSizeBytes) {
      errorBox.textContent = "File is too large. Max size is 8MB.";
      errorBox.classList.remove("d-none");
      fileInput.value = "";
      return;
    }

    croppedMime = file.type;

    const reader = new FileReader();
    reader.onload = function (e) {
      cropImage.src = e.target.result;
      cropWrap.classList.remove("d-none");

      if (cropper) cropper.destroy();
      cropper = new Cropper(cropImage, {
        aspectRatio: 1, // square, matches LOA ID photo convention
        viewMode: 1,
        dragMode: "move",
        autoCropArea: 1,
        cropBoxResizable: false,
        cropBoxMovable: false,
        guides: false,
        center: false,
        highlight: false,
        background: false,
      });

      nextBtn.disabled = false;
    };
    reader.onerror = function () {
      errorBox.textContent = "Failed to read the selected file.";
      errorBox.classList.remove("d-none");
    };
    reader.readAsDataURL(file);
  });

  zoomInBtn?.addEventListener("click", () => cropper?.zoom(0.1));
  zoomOutBtn?.addEventListener("click", () => cropper?.zoom(-0.1));
  resetBtn?.addEventListener("click", () => cropper?.reset());

  nextBtn?.addEventListener("click", function () {
    if (!cropper) return;
    const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });

    canvas.toBlob(
      function (blob) {
        croppedBlob = blob;
        confirmImg.src = URL.createObjectURL(blob);
        showStep(2);
      },
      croppedMime,
      0.9,
    );
  });

  backBtn?.addEventListener("click", () => showStep(1));

  saveBtn?.addEventListener("click", async function () {
    if (!croppedBlob) return;

    const employeeId =
      document.getElementById("editEmployeeId")?.value ||
      window.currentEmployee?.employee_id;

    if (!employeeId) {
      Swal.fire("Error", "Missing employee ID.", "error");
      return;
    }

    loadingOverlay.classList.remove("d-none");
    saveBtn.disabled = true;

    try {
      const ext = croppedMime === "image/png" ? "png" : "jpg";
      const formData = new FormData();
      formData.set("employee_id", employeeId);
      formData.set("overwrite", "1");
      formData.set("picture", croppedBlob, `id_picture.${ext}`);

      const res = await fetch("functions/upload_employee_picture.php", {
        method: "POST",
        body: formData,
      });
      const data = await res.json();

      if (!data.success) {
        loadingOverlay.classList.add("d-none");
        saveBtn.disabled = false;
        Swal.fire("Error", data.message || "Failed to save picture.", "error");
        return;
      }

      // Picture upload succeeded — now log this as a "CHANGE EMPLOYEE
      // PICTURE" history entry. Separate endpoint on purpose, since
      // upload_employee_picture.php is shared by other flows (e.g.
      // LOA verification) that shouldn't produce this history line.
      try {
        await fetch("functions/log_picture_change_history.php", {
          method: "POST",
          body: new URLSearchParams({ employee_id: employeeId }),
        });
      } catch (histErr) {
        // Non-fatal: the picture itself saved fine, so don't block
        // the user on a history-logging failure -- just note it.
        console.error("Failed to log picture change history:", histErr);
      }

      loadingOverlay.classList.add("d-none");
      saveBtn.disabled = false;
      bsModal?.hide();
      Swal.fire("Saved", "Employee picture updated.", "success").then(() => {
        window.location.href = "promodizers.php";
      });
    } catch (err) {
      loadingOverlay.classList.add("d-none");
      saveBtn.disabled = false;
      console.error(err);
      Swal.fire("Error", "Request failed.", "error");
    }
  });
});
