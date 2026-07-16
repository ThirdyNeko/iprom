<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

<style>
.verify-steps { border-bottom: 1px solid #dee2e6; padding-bottom: 8px; }
.step-item {
  flex: 1; text-align: center; font-size: 13px; font-weight: 600;
  color: #adb5bd; position: relative; padding-bottom: 6px;
  transition: color 0.2s ease;
}
.step-item.active { color: #2d68c4; }
.step-item.active::after {
  content: ""; position: absolute; bottom: -8px; left: 0; right: 0;
  height: 3px; background: #2d68c4; border-radius: 2px;
}
.id-guide-frame {
  width: 140px; height: 170px; border: 2px dashed #2d68c4; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; background: #f4f8ff;
}
.loa-code-box {
  width: 42px;
  height: 50px;
  text-align: center;
  font-size: 20px;
  font-weight: 700;
  padding: 0;
}
.loa-letter-box { text-transform: uppercase; }
.loa-code-dash {
  font-size: 24px;
  font-weight: 700;
  color: #6c757d;
}

/* ── Crop UI (Step 2) ──────────────────────────────────── */
.crop-outer {
  position: relative;
  width: 260px;
  height: 316px; /* matches the 140:170 guide-frame aspect ratio */
  margin: 0 auto;
}
.crop-frame {
  width: 100%;
  height: 100%;
  background: #000;
  overflow: hidden;
  border-radius: 8px;
}
.crop-frame img {
  display: block;
  /* Cropper.js takes over sizing once initialized */
  max-width: 100%;
}
/* Face/shoulders outline drawn on top of the crop frame -- purely
   visual, so it must never intercept drag/zoom interactions. */
.crop-guide-overlay {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 5;
}
.crop-guide-overlay ellipse,
.crop-guide-overlay path {
  fill: none;
  stroke: rgba(255, 255, 255, 0.85);
  stroke-width: 2;
  stroke-dasharray: 6 5;
}

/* ── Step transition animation ─────────────────────────── */
.verify-step {
  opacity: 1;
  transform: translateX(0);
}
.verify-step-fade-out {
  opacity: 0 !important;
  transform: translateX(-10px);
  transition: opacity 0.18s ease, transform 0.18s ease;
}
.verify-step-fade-in {
  animation: verifyStepFadeIn 0.25s ease;
}
@keyframes verifyStepFadeIn {
  from { opacity: 0; transform: translateX(10px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* ── Loading overlay (blocks interaction during requests) ── */
.modal-body { position: relative; }
.verify-loading-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.85);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 20;
  border-radius: 0.375rem;
  animation: verifyOverlayFadeIn 0.15s ease;
}
@keyframes verifyOverlayFadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}
.verify-loading-text {
  color: #2d68c4;
  font-weight: 600;
  font-size: 14px;
}
</style>

<div class="modal fade" id="verifyLOAModal" tabindex="-1" aria-hidden="true"
  data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Verify Letter of Advice</h5>
        <button type="button" class="btn-close" id="verifyCloseXBtn" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <!-- Blocks all interaction while a request is in flight -->
        <div class="verify-loading-overlay d-none" id="verifyLoadingOverlay">
          <div class="spinner-border text-primary mb-2" role="status"></div>
          <div class="verify-loading-text" id="verifyLoadingText">Processing...</div>
        </div>

        <div class="d-flex justify-content-between mb-4 verify-steps">
          <div class="step-item active" data-step="1">1. LOA Code</div>
          <div class="step-item" data-step="2">2. ID Picture</div>
          <div class="step-item" data-step="3">3. Result</div>
        </div>

        <!-- STEP 1: LOA CODE -->
        <div class="verify-step" id="verifyStep1">
          <label class="form-label d-block text-center">Enter the LOA Code</label>

          <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap" id="loaCodeBoxes">
            <input type="text" maxlength="1" inputmode="text"
              class="form-control loa-code-box loa-letter-box" data-group="letter" data-index="0">
            <input type="text" maxlength="1" inputmode="text"
              class="form-control loa-code-box loa-letter-box" data-group="letter" data-index="1">
            <input type="text" maxlength="1" inputmode="text"
              class="form-control loa-code-box loa-letter-box" data-group="letter" data-index="2">
            <input type="text" maxlength="1" inputmode="text"
              class="form-control loa-code-box loa-letter-box" data-group="letter" data-index="3">

            <span class="loa-code-dash">&ndash;</span>

            <input type="text" maxlength="1" inputmode="numeric"
              class="form-control loa-code-box loa-digit-box" data-group="digit" data-index="0">
            <input type="text" maxlength="1" inputmode="numeric"
              class="form-control loa-code-box loa-digit-box" data-group="digit" data-index="1">
            <input type="text" maxlength="1" inputmode="numeric"
              class="form-control loa-code-box loa-digit-box" data-group="digit" data-index="2">
            <input type="text" maxlength="1" inputmode="numeric"
              class="form-control loa-code-box loa-digit-box" data-group="digit" data-index="3">
            <input type="text" maxlength="1" inputmode="numeric"
              class="form-control loa-code-box loa-digit-box" data-group="digit" data-index="4">
            <input type="text" maxlength="1" inputmode="numeric"
              class="form-control loa-code-box loa-digit-box" data-group="digit" data-index="5">
          </div>

          <!-- Composed value ("ABCD-123456") kept here and read by verify_loa.js -->
          <input type="hidden" id="loaCodeInput">

          <div id="loaCodeError" class="text-danger small mt-3 text-center d-none"></div>
        </div>

        <!-- STEP 2: ID PICTURE -->
        <div class="verify-step d-none" id="verifyStep2">
          <div class="row g-3">
            <div class="col-md-5 text-center">
              <p class="fw-semibold mb-2">Photo Guide</p>
              <div class="id-guide-frame mx-auto mb-2">
                <svg width="70" height="90" viewBox="0 0 70 90">
                  <circle cx="35" cy="28" r="18" fill="#c8d8f2"/>
                  <path d="M8 88 C8 60 62 60 62 88 Z" fill="#c8d8f2"/>
                </svg>
              </div>
              <ul class="text-start small text-muted ps-3 mb-0">
                <li>Face clearly visible, no sunglasses or mask</li>
                <li>Plain, well-lit background</li>
                <li>Head and shoulders centered in frame</li>
                <li>Upload JPEG, JPG, or PNG only</li>
              </ul>
            </div>

            <div class="col-md-7">
              <div id="existingPictureWrap" class="d-none text-center mb-3">
                <p class="fw-semibold mb-2">Existing Picture on File</p>
                <img id="existingPictureImg" src="" class="img-fluid rounded border mb-2" style="max-height:180px;">
                <div>
                  <button type="button" class="btn btn-outline-dark btn-sm" id="keepExistingBtn">Keep Existing</button>
                  <button type="button" class="btn btn-warning btn-sm" id="overwriteBtn">Overwrite</button>
                </div>
              </div>

              <div id="uploadWrap">
                <p id="uploadPrompt" class="fw-semibold mb-2">Upload ID Picture</p>
                <input type="file" id="idPictureInput" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                <div id="pictureError" class="text-danger small mt-2 d-none"></div>

                <!-- Crop UI: appears once a file is chosen -->
                <div class="mt-3 d-none" id="cropWrap">
                  <p class="small text-muted mb-2 text-center">Drag to reposition, scroll or use the buttons to zoom</p>
                  <div class="crop-outer">
                    <div class="crop-frame">
                      <img id="cropImage" src="" alt="Crop preview">
                    </div>
                    <svg class="crop-guide-overlay" viewBox="0 0 260 316" xmlns="http://www.w3.org/2000/svg">
                      <ellipse cx="130" cy="118" rx="52" ry="66"/>
                      <path d="M35 300 C35 215 225 215 225 300"/>
                    </svg>
                  </div>
                  <div class="d-flex justify-content-center gap-2 mt-2">
                    <button type="button" class="btn btn-outline-dark btn-sm" id="cropZoomOutBtn" title="Zoom out">
                      <i class="bi bi-zoom-out"></i>
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm" id="cropZoomInBtn" title="Zoom in">
                      <i class="bi bi-zoom-in"></i>
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm" id="cropResetBtn" title="Reset">
                      <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- STEP 3: RESULT -->
        <div class="verify-step d-none" id="verifyStep3">
          <div class="text-center py-3" id="finalizeResult"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary d-none" id="verifyBackBtn">Back</button>
        <button type="button" class="btn btn-primary" id="verifyNextBtn">Next</button>
        <button type="button" class="btn btn-success d-none" id="verifyOkayBtn" data-bs-dismiss="modal">Okay</button>
      </div>
    </div>
  </div>
</div>