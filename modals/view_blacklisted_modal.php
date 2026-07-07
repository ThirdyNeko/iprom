<style>
#viewBlacklistedModal .form-control[readonly],
#viewBlacklistedModal .form-control[readonly],
#viewBlacklistedModal textarea[readonly] {
  background-color: #e9ecef;
  opacity: 1;
  color: #000000;
}
</style>

<div class="modal fade" id="viewBlacklistedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-black">
        <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> Blacklisted Record Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">

          <div class="col-md-3">
            <label class="form-label fw-bold">First Name</label>
            <input type="text" class="form-control" id="vb_first_name" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold">Middle Name</label>
            <input type="text" class="form-control" id="vb_middle_name" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold">Last Name</label>
            <input type="text" class="form-control" id="vb_last_name" readonly>
          </div>

          <div class="col-md-3">
            <label class="form-label fw-bold">Suffix</label>
            <input type="text" class="form-control" id="vb_suffix" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold">Gender</label>
            <input type="text" class="form-control" id="vb_gender" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold">Birthdate</label>
            <input type="text" class="form-control" id="vb_birthday" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold">Marital Status</label>
            <input type="text" class="form-control" id="vb_marital_status" readonly>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-bold">Branch</label>
            <input type="text" class="form-control" id="vb_branch" readonly>
          </div>
          <div class="col-md-4" id="vb_brand_group">
            <label class="form-label fw-bold">Brand</label>
            <input type="text" class="form-control" id="vb_brand" readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold">Region</label>
            <input type="text" class="form-control" id="vb_region" readonly>
          </div>

          <div class="col-md-6" id="vb_employment_status_group">
            <label class="form-label fw-bold">Employment Status</label>
            <input type="text" class="form-control" id="vb_employment_status" readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold">End Date</label>
            <input type="text" class="form-control" id="vb_end_date" readonly>
          </div>

          <div class="col-12">
            <label class="form-label fw-bold">Remarks / Violation</label>
            <textarea class="form-control" id="vb_remarks" rows="3" readonly></textarea>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>