<!-- modals/request_flagging_modal.php -->
<style>
  /* Match the remarks textarea's yellow highlight on any select in this
     modal while it's enabled/selectable (branch/brand/promodiser). */
  #requestFlaggingModal select.form-select:not(:disabled) {
    background-color: #fffbdf;
  }
</style>
<div class="modal fade" id="requestFlaggingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Request Flagging</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div class="row g-2 mb-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Branch</label>
            <select id="fl_branch_select" class="form-select"></select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Brand</label>
            <select id="fl_brand_select" class="form-select" disabled>
              <option value="">Select a branch first...</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Promodiser</label>
            <select id="fl_employee_select" class="form-select" disabled>
              <option value="">Select a brand first...</option>
            </select>
          </div>
        </div>

        <input type="hidden" id="fl_employee_id">

        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label">First Name</label>
            <input type="text" id="fl_first_name" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-3">
            <label class="form-label">Middle Name</label>
            <input type="text" id="fl_middle_name" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-3">
            <label class="form-label">Last Name</label>
            <input type="text" id="fl_last_name" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-3">
            <label class="form-label">Suffix</label>
            <input type="text" id="fl_suffix" class="form-control" readonly style="background:#e9ecef;">
          </div>

          <div class="col-md-4">
            <label class="form-label">Date Hired</label>
            <input type="text" id="fl_date_hired" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-4">
            <label class="form-label">Gender</label>
            <input type="text" id="fl_gender" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-4">
            <label class="form-label">Marital Status</label>
            <input type="text" id="fl_marital_status" class="form-control" readonly style="background:#e9ecef;">
          </div>

          <div class="col-md-6">
            <label class="form-label">Employment Status</label>
            <input type="text" id="fl_employment_status" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-6">
            <label class="form-label">Sub Status</label>
            <input type="text" id="fl_sub_status" class="form-control" readonly style="background:#e9ecef;">
          </div>

          <div class="col-md-12">
            <label class="form-label fw-semibold">Remarks / Reason for Flagging</label>
            <textarea id="fl_remarks" class="form-control" rows="3" maxlength="100" style="background:#fffbdf;"
                      placeholder="Type Here..."></textarea>
            <div class="form-text text-end"><span id="fl_remarks_count">0</span>/100</div>
          </div>

          <div class="col-md-12">
            <label class="form-label fw-semibold">Attachments (up to 3 images)</label>
            <input type="file" id="fl_attachments_input" class="form-control"
                   accept="image/png,image/jpeg,image/jpg" multiple>
            <div class="form-text">JPEG, JPG, or PNG only — max 3 images, 5MB each.</div>
            <div id="fl_attachments_preview" class="d-flex flex-wrap gap-2 mt-2"></div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="submitFlaggingRequestBtn" class="btn btn-danger" disabled>
          <i class="bi bi-send me-1"></i>Submit Request
        </button>
      </div>
    </div>
  </div>
</div>