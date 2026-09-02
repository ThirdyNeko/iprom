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

$can_request_blacklist = in_array(strtolower($user_role), ['audit_manager', 'audit_supervisor', 'branch_manager']);
$can_action_requests   = in_array(strtolower($user_role), ['admin', 'super_admin']);
?>

<style>
    #BLtable th,
    #BLtable td {
        border-right: 1px solid #dee2e6;
    }
    #BLtable.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }
    #BLtable th {
        text-align: center;
        vertical-align: middle;
        background-color: #2d68c4;
        color: white;
    }

    .card-body .row.g-2 .col {
        min-width: 160px;
    }

    .filter-control {
        height: 32px !important;
        font-size: 14px;
    }

    #BLtable td {
        font-size: 14px;
        text-align: center !important;
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

    .status-badge-pending  { background:#ffc107; color:#212529; }
    .status-badge-approved { background:#198754; color:#fff; }
    .status-badge-rejected { background:#dc3545; color:#fff; }
</style>

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Blacklist Requests</h4>

            <?php if ($can_request_blacklist): ?>
                <button type="button" id="openRequestBlacklistBtn" class="btn btn-danger btn-sm">
                    <i class="bi bi-slash-circle me-1"></i>Request Blacklist
                </button>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="clear-input">
                            <input type="text" id="filterBLName"
                                class="form-control form-control-sm filter-control"
                                placeholder="Promodiser, Branch, Status, Requested By">
                            <button type="button" class="clear-btn" data-target="filterBLName">×</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="BLtable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th>Promodiser</th>
                                <th>Branch</th>
                                <th>Brand</th>
                                <th>Employment Status</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Requested By</th>
                                <th>Requested Date</th>
                                <?php if ($can_action_requests): ?>
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

<script>
    // UI convenience only — endpoints re-check $_SESSION['role'] server-side.
    const CURRENT_USER_ROLE   = <?php echo json_encode($user_role); ?>;
    const CURRENT_USER_BRANCH = <?php echo json_encode($user_branch); ?>;
    const CAN_REQUEST_BLACKLIST = <?php echo json_encode($can_request_blacklist); ?>;
    const CAN_ACTION_REQUESTS  = <?php echo json_encode($can_action_requests); ?>;
</script>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/blacklist_request/blacklist_request.js"></script>
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

<?php include 'modals/change_password_modal.php'; ?>
<?php include 'modals/request_blacklist_modal.php'; ?>