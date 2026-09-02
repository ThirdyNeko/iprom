<!-- modals/request_blacklist_modal.php -->
<div class="modal fade" id="requestBlacklistModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-slash-circle me-2"></i>Request Blacklist</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div class="row g-2 mb-2">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Branch</label>
            <select id="bl_branch_select" class="form-select"></select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Promodiser</label>
            <select id="bl_employee_select" class="form-select" disabled>
              <option value="">Select a branch first...</option>
            </select>
          </div>
        </div>

        <input type="hidden" id="bl_employee_id">

        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">First Name</label>
            <input type="text" id="bl_first_name" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-4">
            <label class="form-label">Middle Name</label>
            <input type="text" id="bl_middle_name" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-4">
            <label class="form-label">Last Name</label>
            <input type="text" id="bl_last_name" class="form-control" readonly style="background:#e9ecef;">
          </div>

          <div class="col-md-3">
            <label class="form-label">Suffix</label>
            <input type="text" id="bl_suffix" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-3">
            <label class="form-label">Birthday</label>
            <input type="text" id="bl_birthday" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-3">
            <label class="form-label">Gender</label>
            <input type="text" id="bl_gender" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-3">
            <label class="form-label">Marital Status</label>
            <input type="text" id="bl_marital_status" class="form-control" readonly style="background:#e9ecef;">
          </div>

          <div class="col-md-4">
            <label class="form-label">Branch</label>
            <input type="text" id="bl_branch" class="form-control" readonly style="background:#e9ecef;">
            <input type="hidden" id="bl_branch_code">
          </div>
          <div class="col-md-4">
            <label class="form-label">Brand</label>
            <input type="text" id="bl_brand" class="form-control" readonly style="background:#e9ecef;">
          </div>
          <div class="col-md-4">
            <label class="form-label">Employment Status</label>
            <input type="text" id="bl_employment_status" class="form-control" readonly style="background:#e9ecef;">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">End Date</label>
            <input type="date" id="bl_end_date" class="form-control" style="background:#fffbdf;">
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Remarks / Reason for Blacklist</label>
            <textarea id="bl_remarks" class="form-control" rows="3" style="background:#fffbdf;"
                      placeholder="Explain the reason for this blacklist request..."></textarea>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="submitBlacklistRequestBtn" class="btn btn-danger" disabled>
          <i class="bi bi-send me-1"></i>Submit Request
        </button>
      </div>
    </div>
  </div>
</div>