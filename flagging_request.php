<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

$user_role   = $_SESSION['role'] ?? '';
$user_branch = $_SESSION['branch'] ?? ''; // comma-delimited string, explode when filtering
$role_lower  = strtolower($user_role);

$is_audit = in_array($role_lower, ['audit_manager', 'audit_supervisor']);
$is_admin = in_array($role_lower, ['admin', 'super_admin']);

// Flagging Requests tab — audit roles, admin/super_admin (so approvals
// have somewhere to happen), and branch_manager (so they can still
// submit requests)
$can_view_flagging_tab = $is_audit || $is_admin || $role_lower === 'branch_manager';

// Submitting a new flagging request — audit roles and branch_manager
$can_request_flagging = $is_audit || $role_lower === 'branch_manager';

// Approve / reject pending flagging requests
$can_action_flagging_requests = $is_admin;
?>

<style>
    /* --- Flagging Requests tab --- */
    #FRtable th,
    #FRtable td {
        border-right: 1px solid #dee2e6;
    }
    #FRtable.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }
    #FRtable th {
        text-align: center;
        vertical-align: middle;
        background-color: #2d68c4;
        color: white;
    }
    #FRtable td {
        font-size: 14px;
        text-align: center !important;
    }
    #FRtable tbody tr {
        cursor: pointer;
    }
    #FRtable td.fr-actions-col {
        cursor: default;
    }

    .card-body .row.g-2 .col {
        min-width: 160px;
    }

    .filter-control {
        height: 32px !important;
        font-size: 14px;
    }

    .clear-input {
        position: relative;
    }

    .clear-input input {
        padding-right: 28px;
    }

    .clear-btn {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        border: none;
        background: transparent;
        font-size: 18px;
        line-height: 1;
        color: #999;
        cursor: pointer;
        padding: 0;
    }

    .clear-btn:hover {
        color: #333;
    }

    .status-badge-flagged   { background:#dc3545; color:#fff; }
    .status-badge-unflagged { background:#6c757d; color:#fff; }
</style>

<?php if ($can_view_flagging_tab): ?>
<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Flagging Request Management</h4>

            <?php if ($can_request_flagging): ?>
                <button type="button" id="openRequestFlaggingBtn" class="btn btn-danger btn-sm">
                    <i class="bi bi-slash-circle me-1"></i>Request Flagging
                </button>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="clear-input">
                            <input type="text" id="filterFRName"
                                class="form-control form-control-sm filter-control"
                                placeholder="Name, Branch, Brand, Requested by">
                            <button type="button" class="clear-btn" data-target="filterFRName">×</button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select id="filterFRStatus" class="form-select form-select-sm filter-control">
                            <option value="">All</option>
                            <option value="Flagged">Flagged</option>
                            <option value="Unflagged">Unflagged</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table id="FRtable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th>Name</th>
                                <th>Branch</th>
                                <th>Brand</th>
                                <th>Employment Status</th>
                                <th>Sub Status</th>
                                <th>Status</th>
                                <th>Requested by</th>
                                <th>Requested Date</th>
                                <?php if ($can_action_flagging_requests || $can_request_flagging): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<?php endif; ?>

<script>
    // UI convenience only — endpoints re-check $_SESSION['role'] server-side.
    const CURRENT_USER_ROLE            = <?php echo json_encode($user_role); ?>;
    const CURRENT_USER_BRANCH          = <?php echo json_encode($user_branch); ?>;
    const CURRENT_USER_NAME            = <?php echo json_encode($_SESSION['fullname'] ?? ($_SESSION['username'] ?? '')); ?>;
    const CAN_REQUEST_FLAGGING         = <?php echo json_encode($can_request_flagging); ?>;
    const CAN_ACTION_FLAGGING_REQUESTS = <?php echo json_encode($can_action_flagging_requests); ?>;
</script>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<?php if ($can_view_flagging_tab): ?>
<script src="assets/js/flagging_request/flagging_request.js"></script>
<?php endif; ?>

<script>
document.querySelectorAll(".clear-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    const targetId = btn.getAttribute("data-target");
    const input = document.getElementById(targetId);
    input.value = "";
    input.dispatchEvent(new Event("input"));
  });
});
</script>

<?php if ($can_view_flagging_tab): ?>
<?php include 'modals/request_flagging_modal.php'; ?>
<?php include 'modals/view_flagging_request_modal.php'; ?>
<?php endif; ?>

<?php include 'modals/change_password_modal.php'; ?>