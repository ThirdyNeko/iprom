<style>
  /* ── two-pane branch layout ── */
  #v_branch {
    display: flex;
    height: 250px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
  }

  .branch-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
  }

  .branch-col-header {
    padding: 4px 8px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6c757d;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    flex-shrink: 0;
  }

  .branch-pane {
    flex: 1;
    overflow-y: auto;
    padding: 4px;
  }

  .branch-col-divider {
    width: 1px;
    background: #dee2e6;
    flex-shrink: 0;
  }

  .branch-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 1px 2px;
    box-sizing: border-box;
  }
</style>

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
                        <div id="v_role_wrapper">
                            <input type="text" id="v_role" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Date Created</label>
                        <input type="text" id="v_created_at" class="form-control" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Date Updated</label>
                        <input type="text" id="v_updated_at" class="form-control" readonly>
                    </div>

                    <!-- Branches -->
                    <div class="col-12">

                        <label class="form-label fw-semibold mb-0">Branches</label>
                        <small id="branchCounter" class="text-muted ms-1">Selected: 0</small>

                        <input type="text"
                               id="branchSearch"
                               class="form-control my-1"
                               placeholder="Search branches..."
                               style="text-transform: uppercase;"
                               disabled>

                        <div id="v_branch"></div>

                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="resetPasswordBtn" class="btn btn-warning" style="display:none;">
                    Reset Password
                </button>
                <button type="button" id="saveChangesBtn" class="btn btn-success" disabled>
                    Save Changes
                </button>
            </div>

        </div>
    </div>
</div>

<script>
  const SESSION_ROLE = "<?= $_SESSION['role'] ?? '' ?>";
</script>
<script src="assets/js/users/users_modal.js"></script>