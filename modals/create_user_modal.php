<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <form action="functions/create_user.php" method="POST">
                
                <div class="modal-header">
                    <h5 class="modal-title">Create User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text"
                               name="username"
                               class="form-control text-uppercase"
                               style="text-transform: uppercase;"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="" disabled selected>Select Role</option>
                            <option value="admin">ADMIN</option>
                            <option value="hr">HUMAN RESOURCES</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <input type="text"
                               name="branch"
                               class="form-control"
                               placeholder="Optional">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Brand</label>
                        <input type="text"
                               name="brand"
                               class="form-control"
                               placeholder="Optional">
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Create
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>