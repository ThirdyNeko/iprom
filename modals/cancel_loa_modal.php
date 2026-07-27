<style>
/* Reuses .loa-code-box / .loa-code-dash / .verify-loading-overlay base
   styles already defined alongside verify_loa_modal.php on the same
   page. Only the bits unique to this modal are declared here. */
.cancel-loa-warning {
  font-size: 15px;
}
#cancelLOAModal .modal-body {
  padding: 2rem 2.5rem;
}
#cancelLOAModal .loa-code-box {
  margin: 0 2px;
}
</style>

<div class="modal fade" id="cancelLOAModal" tabindex="-1" aria-hidden="true"
  data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold text-danger">Cancel Letter of Advice</h5>
        <button type="button" class="btn-close" id="cancelLoaCloseXBtn" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body position-relative">

        <!-- Blocks all interaction while a request is in flight -->
        <div class="verify-loading-overlay d-none" id="cancelLoaLoadingOverlay">
          <div class="spinner-border text-primary mb-2" role="status"></div>
          <div class="verify-loading-text" id="cancelLoaLoadingText">Processing...</div>
        </div>

        <p class="text-center mb-3 cancel-loa-warning">
          This will <strong>cancel</strong> this LOA verification. This action cannot be undone.
          <br>To confirm, enter the LOA code for <span id="cancelLoaEmployeeName" class="fw-semibold"></span> below.
        </p>

        <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap" id="cancelLoaCodeBoxes">
          <input type="text" maxlength="1" inputmode="text"
            class="form-control loa-code-box cancel-letter-box" data-group="letter" data-index="0">
          <input type="text" maxlength="1" inputmode="text"
            class="form-control loa-code-box cancel-letter-box" data-group="letter" data-index="1">
          <input type="text" maxlength="1" inputmode="text"
            class="form-control loa-code-box cancel-letter-box" data-group="letter" data-index="2">
          <input type="text" maxlength="1" inputmode="text"
            class="form-control loa-code-box cancel-letter-box" data-group="letter" data-index="3">

          <span class="loa-code-dash">&ndash;</span>

          <input type="text" maxlength="1" inputmode="numeric"
            class="form-control loa-code-box cancel-digit-box" data-group="digit" data-index="0">
          <input type="text" maxlength="1" inputmode="numeric"
            class="form-control loa-code-box cancel-digit-box" data-group="digit" data-index="1">
          <input type="text" maxlength="1" inputmode="numeric"
            class="form-control loa-code-box cancel-digit-box" data-group="digit" data-index="2">
          <input type="text" maxlength="1" inputmode="numeric"
            class="form-control loa-code-box cancel-digit-box" data-group="digit" data-index="3">
          <input type="text" maxlength="1" inputmode="numeric"
            class="form-control loa-code-box cancel-digit-box" data-group="digit" data-index="4">
          <input type="text" maxlength="1" inputmode="numeric"
            class="form-control loa-code-box cancel-digit-box" data-group="digit" data-index="5">
        </div>

        <!-- Composed value ("ABCD-123456") kept here and read by cancel_loa.js -->
        <input type="hidden" id="cancelLoaCodeInput">

        <div id="cancelLoaCodeError" class="text-danger small mt-3 text-center d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Never mind</button>
        <button type="button" class="btn btn-danger" id="cancelLoaConfirmBtn">Delete LOA</button>
      </div>
    </div>
  </div>
</div>