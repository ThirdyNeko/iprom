<!-- modals/view_blacklist_request_modal.php -->
<div class="modal fade" id="viewBlacklistRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Blacklist Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 id="vbr_full_name" class="fw-bold mb-0"></h6>
          <span id="vbr_status_badge"></span>
        </div>

        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label text-muted small mb-0">Birthday</label>
            <div id="vbr_birthday" class="fw-semibold"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted small mb-0">Branch</label>
            <div id="vbr_branch" class="fw-semibold"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted small mb-0">Brand</label>
            <div id="vbr_brand" class="fw-semibold"></div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-muted small mb-0">Employment Status</label>
            <div id="vbr_employment_status" class="fw-semibold"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small mb-0">End Date</label>
            <div id="vbr_end_date" class="fw-semibold"></div>
          </div>

          <div class="col-12">
            <hr class="my-2">
          </div>

          <div class="col-md-6">
            <label class="form-label text-muted small mb-0">Requested By</label>
            <div id="vbr_requested_by" class="fw-semibold"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small mb-0">Requested Date</label>
            <div id="vbr_requested_date" class="fw-semibold"></div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-muted small mb-0">Approved/Rejected By</label>
            <div id="vbr_approved_by" class="fw-semibold">—</div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small mb-0">Approved/Rejected Date</label>
            <div id="vbr_approved_date" class="fw-semibold">—</div>
          </div>

          <div class="col-12">
            <label class="form-label text-muted small mb-0">Remarks / Reason</label>
            <div id="vbr_remarks" class="border rounded p-2" style="background:#f8f9fa; min-height:60px;"></div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>