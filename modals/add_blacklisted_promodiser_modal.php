<!-- Add Blacklisted (Promodiser) Modal -->
<style>
#addBlacklistedPromodiserModal .form-control:not([readonly]):not([disabled]),
#addBlacklistedPromodiserModal .form-select:not([disabled]) {
    background-color: #fffbdf !important; /* editable */
    opacity: 1;
}
#addBlacklistedPromodiserModal .form-control[readonly],
#addBlacklistedPromodiserModal .form-control[disabled],
#addBlacklistedPromodiserModal .form-select[disabled] {
    background-color: #e9ecef !important; /* readonly / disabled */
    opacity: 1;
    cursor: not-allowed;
}
#addBlacklistedPromodiserModal .form-control:focus,
#addBlacklistedPromodiserModal .form-select:focus {
    box-shadow: 0 0 0 0.15rem rgba(255, 193, 7, 0.25);
    border-color: #ffc107;
}
</style>

<div class="modal fade" id="addBlacklistedPromodiserModal" tabindex="-1" aria-labelledby="addBlacklistedPromodiserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="addBlacklistedPromodiserModalLabel">Add Blacklisted Person (Promodiser)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addBlacklistedPromodiserForm" novalidate>

          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Branch <span class="text-danger">*</span></label>
              <select id="blp_branch" class="form-select" required>
                <option value="" selected disabled>Select Branch</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Promodiser <span class="text-danger">*</span></label>
              <select id="blp_employee_select" class="form-select" disabled>
                <option value="">Select a branch first...</option>
              </select>
            </div>
          </div>

          <input type="hidden" id="blp_employee_id">
          <input type="hidden" id="blp_branch_code">

          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">First Name</label>
              <input type="text" id="blp_first_name" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label">Middle Name</label>
              <input type="text" id="blp_middle_name" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name</label>
              <input type="text" id="blp_last_name" class="form-control" readonly>
            </div>

            <div class="col-md-3">
              <label class="form-label">Suffix</label>
              <input type="text" id="blp_suffix" class="form-control" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Birthdate</label>
              <input type="text" id="blp_birthdate" class="form-control" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Gender</label>
              <input type="text" id="blp_gender" class="form-control" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Marital Status</label>
              <input type="text" id="blp_marital_status" class="form-control" readonly>
            </div>

            <div class="col-md-4">
              <label class="form-label">Branch</label>
              <input type="text" id="blp_branch_display" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label">Brand</label>
              <input type="text" id="blp_brand" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label">Employment Status</label>
              <input type="text" id="blp_employment_status" class="form-control" readonly>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
              <input type="date" id="blp_end_date" class="form-control" required>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Remarks / Violation <span class="text-danger">*</span></label>
              <textarea id="blp_remarks" class="form-control" rows="3" maxlength="100" required
                        placeholder="Explain the reason for this blacklist entry..."></textarea>
              <div class="d-flex justify-content-end">
                <small class="text-muted"><span id="blp_remarks_count">0</span>/100</small>
              </div>
            </div>
          </div>

        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveBlacklistedPromodiserBtn" disabled>Save</button>
      </div>
    </div>
  </div>
</div>