<div class="modal fade" id="userViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="v_username">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input type="text" id="v_first_name" class="form-control" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" id="v_last_name" class="form-control" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Position</label>
                        <input type="text" id="v_position" class="form-control" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <input type="text" id="v_role" class="form-control" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Created At</label>
                        <input type="text" id="v_created_at" class="form-control" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Updated At</label>
                        <input type="text" id="v_updated_at" class="form-control" readonly>
                    </div>

                    <!-- Branches: full width, editable checkboxes -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Branches</label>
                        <div id="v_branch"
                             style="max-height: 180px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; padding: 8px;">
                            <!-- populated by JS -->
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="saveBranchBtn" class="btn btn-primary">
                    <i class="bi bi-save"></i> Save Branches
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<script src="assets/js/users/users_modal.js"></script>