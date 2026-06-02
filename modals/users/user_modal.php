<div class="modal fade" id="userViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
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
                        <label class="form-label">Branches</label>
                        <div id="v_branch" class="form-control branch-checkbox-group" 
                            style="height: 80px; overflow-y: auto; padding: 4px; background-color: #e9ecef;">
                        </div>
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

                </div>
            </div>

        </div>
    </div>
</div>

<script src="assets/js/users/users_modal.js"></script>
