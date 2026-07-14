<style>
.verify-steps { border-bottom: 1px solid #dee2e6; padding-bottom: 8px; }
.step-item {
  flex: 1; text-align: center; font-size: 13px; font-weight: 600;
  color: #adb5bd; position: relative; padding-bottom: 6px;
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
</style>

<div class="modal fade" id="verifyLOAModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Verify Letter of Advice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
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
                <div class="mt-2 text-center d-none" id="previewWrap">
                  <img id="previewImg" class="img-fluid rounded border" style="max-height:180px;">
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
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-outline-primary d-none" id="verifyBackBtn">Back</button>
        <button type="button" class="btn btn-primary" id="verifyNextBtn">Next</button>
      </div>
    </div>
  </div>
</div>