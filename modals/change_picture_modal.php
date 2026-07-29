<style>
#changePictureModal .verify-loading-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.85);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 20;
  border-radius: 0.375rem;
}
#changePictureModal .modal-body {
  position: relative;
}
#changePictureModal .verify-loading-text {
  color: #2d68c4;
  font-weight: 600;
  font-size: 14px;
}
#changePictureModal .crop-outer {
  position: relative;
  width: 260px;
  height: 260px;
  margin: 0 auto;
}
#changePictureModal .crop-frame {
  width: 100%;
  height: 100%;
  background: #000;
  overflow: hidden;
  border-radius: 8px;
}
#changePictureModal .crop-frame img {
  display: block;
  max-width: 100%;
}
#changePictureModal .crop-guide-overlay {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 5;
}
#changePictureModal .crop-guide-overlay ellipse,
#changePictureModal .crop-guide-overlay path {
  fill: none;
  stroke: rgba(255, 255, 255, 0.85);
  stroke-width: 2;
  stroke-dasharray: 6 5;
}
#changePictureModal .confirm-picture-frame {
  width: 180px;
  height: 180px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #dee2e6;
  margin: 0 auto;
  background: #f8f9fa;
}
#changePictureModal .confirm-picture-frame img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
#changePictureModal .verify-steps {
  border-bottom: 1px solid #dee2e6;
  padding-bottom: 8px;
}
#changePictureModal .step-item {
  flex: 1;
  text-align: center;
  font-size: 13px;
  font-weight: 600;
  color: #adb5bd;
  position: relative;
  padding-bottom: 6px;
  transition: color 0.2s ease;
}
#changePictureModal .step-item.active {
  color: #2d68c4;
}
#changePictureModal .step-item.active::after {
  content: "";
  position: absolute;
  bottom: -8px;
  left: 0;
  right: 0;
  height: 3px;
  background: #2d68c4;
  border-radius: 2px;
}
</style>

<div class="modal fade" id="changePictureModal" tabindex="-1" aria-hidden="true"
  data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Change Employee Picture</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="verify-loading-overlay d-none" id="cpLoadingOverlay">
          <div class="spinner-border text-primary mb-2" role="status"></div>
          <div class="verify-loading-text">Processing...</div>
        </div>

        <div class="d-flex justify-content-between mb-4 verify-steps">
          <div class="step-item active" data-cp-step="1">1. Upload &amp; Crop</div>
          <div class="step-item" data-cp-step="2">2. Confirm</div>
        </div>

        <!-- STEP 1: UPLOAD + CROP -->
        <div class="verify-step" id="cpStep1">
          <div class="text-center mb-3">
            <input type="file" id="cpPictureInput" class="form-control"
              accept=".jpg,.jpeg,.png,image/jpeg,image/png">
            <div id="cpPictureError" class="text-danger small mt-2 d-none"></div>
          </div>

          <div class="d-none" id="cpCropWrap">
            <p class="small text-muted mb-2 text-center">Drag to reposition, scroll or use the buttons to zoom</p>
            <div class="crop-outer">
              <div class="crop-frame">
                <img id="cpCropImage" src="" alt="Crop preview">
              </div>
              <svg class="crop-guide-overlay" viewBox="0 0 260 260" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="130" cy="98" rx="58" ry="70"/>
                <path d="M25 250 C25 165 235 165 235 250"/>
              </svg>
            </div>
            <div class="d-flex justify-content-center gap-2 mt-2">
              <button type="button" class="btn btn-outline-dark btn-sm" id="cpZoomOutBtn" title="Zoom out">
                <i class="bi bi-zoom-out"></i>
              </button>
              <button type="button" class="btn btn-outline-dark btn-sm" id="cpZoomInBtn" title="Zoom in">
                <i class="bi bi-zoom-in"></i>
              </button>
              <button type="button" class="btn btn-outline-dark btn-sm" id="cpResetBtn" title="Reset">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
              </button>
            </div>
          </div>
        </div>

        <!-- STEP 2: CONFIRM -->
        <div class="verify-step d-none" id="cpStep2">
          <p class="text-center fw-semibold mb-1">Confirm New Picture</p>
          <p class="text-center text-muted small mb-3">
            This will replace the employee's picture on file.
          </p>
          <div class="confirm-picture-frame">
            <img id="cpConfirmPicture" src="" alt="New picture">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary d-none" id="cpBackBtn">Back</button>
        <button type="button" class="btn btn-primary" id="cpNextBtn" disabled>Next</button>
        <button type="button" class="btn btn-success d-none" id="cpSaveBtn">Save Picture</button>
      </div>
    </div>
  </div>
</div>