<!-- modals/view_flagging_request_modal.php -->
<div class="modal fade" id="viewFlaggingRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Flagging Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 id="vfr_full_name" class="fw-bold mb-0"></h6>
          <span id="vfr_status_badge"></span>
        </div>

        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label text-muted small mb-0">Date Hired</label>
            <div id="vfr_date_hired" class="fw-semibold"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted small mb-0">Branch</label>
            <div id="vfr_branch" class="fw-semibold"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted small mb-0">Brand</label>
            <div id="vfr_brand" class="fw-semibold"></div>
          </div>

          <div class="col-md-4">
            <label class="form-label text-muted small mb-0">Employment Status</label>
            <div id="vfr_employment_status" class="fw-semibold"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted small mb-0">Sub Status</label>
            <div id="vfr_sub_status" class="fw-semibold"></div>
          </div>

          <div class="col-12">
            <hr class="my-2">
          </div>

          <div class="col-md-6">
            <label class="form-label text-muted small mb-0">Requested by</label>
            <div id="vfr_requested_by" class="fw-semibold"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small mb-0">Requested Date</label>
            <div id="vfr_requested_date" class="fw-semibold"></div>
          </div>

          <div class="col-12">
            <label class="form-label text-muted small mb-0">Remarks / Reason</label>
            <div id="vfr_remarks" class="border rounded p-2" style="background:#f8f9fa; min-height:60px;"></div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-muted small mb-0">Unflagged by</label>
            <div id="vfr_unflagged_by" class="fw-semibold">—</div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small mb-0">Unflagged Date</label>
            <div id="vfr_unflagged_date" class="fw-semibold">—</div>
          </div>

          <div class="col-12">
            <label class="form-label text-muted small mb-0">Unflag Remarks</label>
            <div id="vfr_unflag_remarks" class="border rounded p-2" style="background:#f8f9fa; min-height:60px;">—</div>
          </div>

          <div class="col-12">
            <button type="button" id="vfr_view_attachments_btn" class="btn btn-outline-primary btn-sm d-none">
              <i class="bi bi-paperclip me-1"></i>View Attachments
            </button>
            <div id="vfr_attachments_wrapper" class="d-none mt-2">
              <div id="vfr_attachments" class="d-flex flex-wrap gap-2"></div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>