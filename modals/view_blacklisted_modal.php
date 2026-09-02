<!-- modals/view_blacklisted_modal.php -->
<div class="modal fade" id="viewBlacklistedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-lines-fill me-2"></i>Blacklisted Record Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 id="vb_full_name" class="fw-bold mb-0"></h6>
        </div>

        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label text-muted small mb-0">First Name</label>
            <div id="vb_first_name" class="fw-semibold"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small mb-0">Middle Name</label>
            <div id="vb_middle_name" class="fw-semibold"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small mb-0">Last Name</label>
            <div id="vb_last_name" class="fw-semibold"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small mb-0">Suffix</label>
            <div id="vb_suffix" class="fw-semibold"></div>
          </div>

          <div class="col-md-3">
            <label class="form-label text-muted small mb-0">Gender</label>
            <div id="vb_gender" class="fw-semibold"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small mb-0">Birthdate</label>
            <div id="vb_birthday" class="fw-semibold"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small mb-0">Marital Status</label>
            <div id="vb_marital_status" class="fw-semibold"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small mb-0">Region</label>
            <div id="vb_region" class="fw-semibold"></div>
          </div>

          <div class="col-md-4">
            <label class="form-label text-muted small mb-0">Branch</label>
            <div id="vb_branch" class="fw-semibold"></div>
          </div>
          <div class="col-md-4" id="vb_brand_group">
            <label class="form-label text-muted small mb-0">Brand</label>
            <div id="vb_brand" class="fw-semibold"></div>
          </div>
          <div class="col-md-4" id="vb_employment_status_group">
            <label class="form-label text-muted small mb-0">Employment Status</label>
            <div id="vb_employment_status" class="fw-semibold"></div>
          </div>

          <div class="col-md-4">
            <label class="form-label text-muted small mb-0">End Date</label>
            <div id="vb_end_date" class="fw-semibold"></div>
          </div>

          <div class="col-12">
            <hr class="my-2">
          </div>

          <div class="col-12">
            <label class="form-label text-muted small mb-0">Remarks / Violation</label>
            <div id="vb_remarks" class="border rounded p-2" style="background:#f8f9fa; min-height:60px;"></div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>