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

// Promodiser / Direct Hire (the actual Blacklisted table) — audit roles only
$can_view_blacklisted_tabs = $is_audit;

// New Blacklist Requests tab — audit roles, admin/super_admin (so
// approvals have somewhere to happen), and branch_manager (so they can
// still submit requests, same as before this page was merged)
$can_view_requests_tab = $is_audit || $is_admin || $role_lower === 'branch_manager';

// Submitting a new request — audit roles and branch_manager
$can_request_blacklist = $is_audit || $role_lower === 'branch_manager';

// Approve / reject pending requests
$can_action_requests = $is_admin;

// Directly adding a blacklisted record — admin/super_admin only. Audit
// roles can only submit a request via the Blacklist Requests tab; they
// do not get the Add Blacklisted button, modal, or JS included on the
// page at all. (Keeping this separate from $can_view_blacklisted_tabs is
// what keeps add_blacklisted_modal.php's bl_* field IDs out of the DOM
// for audit roles, so they can never collide with request_blacklist_modal.php's
// fields regardless of naming.)
$can_add_blacklisted = $is_admin;

// Pick a sensible default active tab — Blacklist Requests comes first now
$default_tab = $can_view_requests_tab ? 'requests' : ($can_view_blacklisted_tabs ? 'promodiser' : null);
?>

<style>
    #BlacklistedtablePromodiser th,
    #BlacklistedtablePromodiser td,
    #BlacklistedtableDirectHire th,
    #BlacklistedtableDirectHire td {
        border-right: 1px solid #dee2e6;
    }
    #BlacklistedtablePromodiser.table-hover tbody tr:hover > td,
    #BlacklistedtableDirectHire.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }
    #BlacklistedtablePromodiser th,
    #BlacklistedtableDirectHire th
    {
        text-align: center;
        vertical-align: middle;
        background-color: #2d68c4;
        color : white;
    }

    .card-body .row.g-2 .col {
        min-width: 160px;
    }

    .filter-control {
        height: 32px !important;
        font-size: 14px;
    }

    #BlacklistedtablePromodiser td,
    #BlacklistedtableDirectHire td {
        text-align: center;
    }

    #BlacklistedtablePromodiser th:first-child,
    #BlacklistedtablePromodiser td:first-child,
    #BlacklistedtableDirectHire th:first-child,
    #BlacklistedtableDirectHire td:first-child {
        border-left: 1px solid #dee2e6;
    }
    #BlacklistedtablePromodiser td:first-child,
    #BlacklistedtableDirectHire td:first-child {
        text-align: left;
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

    .nav-tabs .nav-link.active {
        font-weight: 600;
        color: #2d68c4;
    }

    /* --- Blacklist Requests tab --- */
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
    #BLtable td {
        font-size: 14px;
        text-align: center !important;
    }
    #BLtable tbody tr {
        cursor: pointer;
    }
    #BLtable td.bl-actions-col {
        cursor: default;
    }
    .status-badge-pending   { background:#ffc107; color:#212529; }
    .status-badge-approved  { background:#198754; color:#fff; }
    .status-badge-rejected  { background:#dc3545; color:#fff; }
    .status-badge-cancelled { background:#6c757d; color:#fff; }
</style>

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Blacklist Management</h4>

            <?php if ($can_view_blacklisted_tabs): ?>
                <div class="d-flex gap-2" id="blacklistedToolbar">
                    <button type="button" class="btn btn-sm btn-primary" id="syncBlacklistBtn">
                        <i class="bi bi-arrow-repeat"></i> Sync from Employees
                    </button>
                    <?php if ($can_add_blacklisted): ?>
                    <button type="button" class="btn btn-sm btn-success" id="addBlacklistedBtn" data-bs-toggle="modal" data-bs-target="#addBlacklistedModal">
                        <i class="bi bi-plus-lg"></i> Add Blacklisted
                    </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($can_request_blacklist): ?>
                <button type="button" id="openRequestBlacklistBtn" class="btn btn-danger btn-sm d-none">
                    <i class="bi bi-slash-circle me-1"></i>Request Blacklist
                </button>
            <?php endif; ?>
        </div>

        <ul class="nav nav-tabs" id="blacklistedTabs" role="tablist">

            <?php if ($can_view_requests_tab): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $default_tab === 'requests' ? 'active' : ''; ?>" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests-pane" type="button" role="tab" aria-controls="requests-pane" aria-selected="<?php echo $default_tab === 'requests' ? 'true' : 'false'; ?>">
                        Blacklist Requests
                    </button>
                </li>
            <?php endif; ?>

            <?php if ($can_view_blacklisted_tabs): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $default_tab === 'promodiser' ? 'active' : ''; ?>" id="promodiser-tab" data-bs-toggle="tab" data-bs-target="#promodiser-pane" type="button" role="tab" aria-controls="promodiser-pane" aria-selected="<?php echo $default_tab === 'promodiser' ? 'true' : 'false'; ?>">
                        Promodiser
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="direct-hire-tab" data-bs-toggle="tab" data-bs-target="#direct-hire-pane" type="button" role="tab" aria-controls="direct-hire-pane" aria-selected="false">
                        Direct Hire
                    </button>
                </li>
            <?php endif; ?>

        </ul>

        <div class="tab-content border border-top-0 rounded-bottom shadow-sm mb-3" id="blacklistedTabsContent">

            <?php if ($can_view_requests_tab): ?>
            <!-- BLACKLIST REQUESTS TAB -->
            <div class="tab-pane fade <?php echo $default_tab === 'requests' ? 'show active' : ''; ?> p-0" id="requests-pane" role="tabpanel" aria-labelledby="requests-tab">
                <div class="card border-0">
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
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select id="filterBLStatus" class="form-select form-select-sm filter-control">
                                    <option value="">All</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
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
                                        <?php if ($can_action_requests || $can_request_blacklist): ?>
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
            <?php endif; ?>

            <?php if ($can_view_blacklisted_tabs): ?>
            <!-- PROMODISER TAB -->
            <div class="tab-pane fade <?php echo $default_tab === 'promodiser' ? 'show active' : ''; ?> p-0" id="promodiser-pane" role="tabpanel" aria-labelledby="promodiser-tab">
                <div class="card border-0">
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <div class="clear-input">
                                    <input type="text" id="filterNamePromodiser"
                                        class="form-control form-control-sm filter-control"
                                        placeholder="First, Middle, or Last Name">
                                    <button type="button" class="clear-btn" data-target="filterNamePromodiser">×</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table id="BlacklistedtablePromodiser" class="table table-striped table-hover align-middle text-center">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Full Name</th>
                                        <th>Branch</th>
                                        <th>Brand</th>
                                        <th>Employment Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DIRECT HIRE TAB -->
            <div class="tab-pane fade p-0" id="direct-hire-pane" role="tabpanel" aria-labelledby="direct-hire-tab">
                <div class="card border-0">
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <div class="clear-input">
                                    <input type="text" id="filterNameDirectHire"
                                        class="form-control form-control-sm filter-control"
                                        placeholder="First, Middle, or Last Name">
                                    <button type="button" class="clear-btn" data-target="filterNameDirectHire">×</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table id="BlacklistedtableDirectHire" class="table table-striped table-hover align-middle text-center">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Full Name</th>
                                        <th>Branch</th>
                                        <th>Brand</th>
                                        <th>Employment Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<script>
    // UI convenience only — endpoints re-check $_SESSION['role'] server-side.
    const CURRENT_USER_ROLE     = <?php echo json_encode($user_role); ?>;
    const CURRENT_USER_BRANCH   = <?php echo json_encode($user_branch); ?>;
    const CURRENT_USER_NAME     = <?php echo json_encode($_SESSION['fullname'] ?? ($_SESSION['username'] ?? '')); ?>;
    const CAN_REQUEST_BLACKLIST = <?php echo json_encode($can_request_blacklist); ?>;
    const CAN_ACTION_REQUESTS   = <?php echo json_encode($can_action_requests); ?>;
</script>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<?php if ($can_view_blacklisted_tabs): ?>
<script src="assets/js/blacklisted/blacklisted.js"></script>
<script src="assets/js/blacklisted/view_blacklisted.js"></script>
<?php endif; ?>

<?php if ($can_add_blacklisted): ?>
<script src="assets/js/blacklisted/add_blacklisted.js"></script>
<?php endif; ?>

<?php if ($can_view_requests_tab): ?>
<script src="assets/js/blacklist_request/blacklist_request.js"></script>
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

<?php if ($can_view_blacklisted_tabs && $can_view_requests_tab): ?>
// Both sets of tabs are visible (audit_manager/audit_supervisor) — the
// "Sync from Employees" / "Add Blacklisted" toolbar only applies to the
// Promodiser/Direct Hire tabs, and "Request Blacklist" only to the
// Blacklist Requests tab. Toggle them based on which tab is active.
const blacklistedTabIds = ['promodiser-tab', 'direct-hire-tab'];

document.querySelectorAll('#blacklistedTabs button[data-bs-toggle="tab"]').forEach(tabBtn => {
    tabBtn.addEventListener('shown.bs.tab', (e) => {
        const isBlacklistedTab = blacklistedTabIds.includes(e.target.id);
        document.getElementById('blacklistedToolbar')?.classList.toggle('d-none', !isBlacklistedTab);
        document.getElementById('openRequestBlacklistBtn')?.classList.toggle('d-none', isBlacklistedTab);
    });
});

// Initial state on page load
const initialIsBlacklistedTab = blacklistedTabIds.includes(
    document.querySelector('#blacklistedTabs .nav-link.active')?.id
);
document.getElementById('blacklistedToolbar')?.classList.toggle('d-none', !initialIsBlacklistedTab);
document.getElementById('openRequestBlacklistBtn')?.classList.toggle('d-none', initialIsBlacklistedTab);
<?php elseif ($can_view_requests_tab): ?>
// Only the Blacklist Requests tab exists for this role — show the button
// (no toolbar exists to conflict with)
document.getElementById('openRequestBlacklistBtn')?.classList.remove('d-none');
<?php endif; ?>
</script>

<?php if ($can_add_blacklisted): ?>
<?php include 'modals/add_blacklisted_modal.php'; ?>
<?php endif; ?>

<?php if ($can_view_blacklisted_tabs): ?>
<?php include 'modals/view_blacklisted_modal.php'; ?>
<?php endif; ?>

<?php if ($can_view_requests_tab): ?>
<?php include 'modals/request_blacklist_modal.php'; ?>
<?php include 'modals/view_blacklist_request_modal.php'; ?>
<?php endif; ?>

<?php include 'modals/change_password_modal.php'; ?>