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
$user_region = $_SESSION['region'] ?? ''; // regional_manager only (users.region)

// ── Branch dropdown options ─────────────────────────────────────
// regional_manager: only branches whose branches.region matches their region.
// branch_manager / staff: narrowed to $_SESSION['branch'] in the loop below.
// admin / super_admin: everything that has an LOA.
$branchSql = "
    SELECT DISTINCT 
        l.branch_code AS branch_code,
        b.branch AS branch
    FROM letters_of_advice l
    LEFT JOIN IPROM.dbo.branches b
        ON l.branch_code = b.branch_code
";
$branchQueryParams = [];

if (strtolower($user_role) === 'regional_manager') {
    if ($user_region !== '') {
        $branchSql .= " WHERE b.region = :region";
        $branchQueryParams[':region'] = $user_region;
    } else {
        // No region assigned -> fail closed, empty dropdown
        $branchSql .= " WHERE 1 = 0";
    }
}

$branchSql .= " ORDER BY b.branch";

$branchStmt = $pdo->prepare($branchSql);
$branchStmt->execute($branchQueryParams);
$branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
    #LOAtable th,
    #LOAtable td {
        border-right: 1px solid #dee2e6;
    }
    #LOAtable.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }
    #LOAtable th
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

    #LOAtable td {
        font-size: 14px;
    }

    #LOAtable th:first-child,
    #LOAtable td:first-child {
        border-left: 1px solid #dee2e6; /* remove extra line at start */
        text-align: center !important;
    }
    /* Employment Status column. Was nth-child(4) when the columns were
       checkbox/Promodiser/Agency/Employment Status/... — shifted to
       nth-child(5) now that Branch sits between Promodiser and Agency. */
    #LOAtable td:nth-child(5) {
        text-align: center !important;
    }
    #LOAtable td:last-child {
        text-align: center !important;
    }

    .clear-input {
        position: relative;
    }

    .clear-input input {
        padding-right: 28px; /* space for X */
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

    /* Bulk verify column stays compact regardless of visibility state */
    #LOAtable th.bulk-verify-col,
    #LOAtable td.bulk-verify-col {
        width: 40px;
    }
</style>

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">For Branch Verification</h4>

            <?php if (in_array(strtolower($user_role), ['admin', 'super_admin'])): ?>
                <button type="button" id="toggleBulkVerifyBtn" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-check2-square me-1"></i>Bulk Verify
                </button>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-2 align-items-end">

                    <!-- NAME SEARCH -->
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="clear-input">
                            <input type="text" id="filterName"
                                class="form-control form-control-sm filter-control"
                                placeholder="Promodiser, Agency, Employment Status, Sub Status">
                            <button type="button" class="clear-btn" data-target="filterName">×</button>
                        </div>
                    </div>

                    <!-- BRANCH FILTER -->
                    <div class="col-md-2">
                        <label class="form-label">Branch</label>
                        <select id="filterBranch" class="form-select filter-control">
                            <option value="">All</option>
                            <?php 
                            $sessionBranches = !empty($_SESSION['branch']) 
                                ? array_map('trim', explode(',', $_SESSION['branch'])) 
                                : [];

                            foreach($branches as $b): 
                                // branch_manager / staff with branch restrictions: only their branches.
                                // (regional_manager is already scoped by region in the query above.)
                                if (strtolower($user_role) !== 'regional_manager'
                                    && !empty($sessionBranches)
                                    && !in_array($b['branch_code'], $sessionBranches)) continue;
                            ?>
                                <option value="<?= htmlspecialchars($b['branch_code']) ?>">
                                    <?= htmlspecialchars($b['branch'] ?? $b['branch_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>
            <div class="card-body">

                <!-- Bulk verify action bar (hidden until Bulk Verify is toggled on) -->
                <div id="bulkVerifyBar" class="d-none align-items-center gap-2 mb-2">
                    <span id="bulkVerifySelectedCount" class="text-muted small">0 selected</span>
                    <button type="button" id="bulkVerifyConfirmBtn" class="btn btn-success btn-sm" disabled>
                        <i class="bi bi-patch-check me-1"></i>Verify Selected
                    </button>
                    <button type="button" id="bulkVerifyCancelBtn" class="btn btn-outline-secondary btn-sm">
                        Cancel
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="LOAtable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th class="bulk-verify-col">
                                    <input type="checkbox" id="bulkVerifySelectAll" class="form-check-input">
                                </th>
                                <th>Promodiser</th>
                                <th>Branch</th>
                                <th>Agency</th>
                                <th>Employment Status</th>
                                <th>Sub Status</th>
                                <th>Effectivity Date</th>
                                <th>Actions</th>
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
    // Session context for client-side UI gating (Verify button visibility, table filtering, etc).
    // NOTE: this is UI convenience only — the actual verify/cancel/bulk-verify endpoints
    // (functions/verify_loa.php, functions/cancel_loa.php, functions/bulk_verify_loa.php, etc)
    // MUST independently re-check $_SESSION['role'] server-side. Never trust
    // this value alone to authorize a write.
    const CURRENT_USER_ROLE   = <?php echo json_encode($user_role); ?>;
    const CURRENT_USER_BRANCH = <?php echo json_encode($user_branch); ?>;
</script>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/verification/verification.js"></script>
<script src="assets/js/verification/verify_loa.js"></script>
<script src="assets/js/verification/cancel_loa.js"></script>
<script>
document.querySelectorAll(".clear-btn").forEach(btn => {
  btn.addEventListener("click", () => {

    const targetId = btn.getAttribute("data-target");
    const input = document.getElementById(targetId);

    input.value = "";

    // trigger DataTable refresh
    input.dispatchEvent(new Event("input"));
  });
});
</script>

<?php include 'modals/change_password_modal.php'; ?>
<?php include 'modals/verify_loa_modal.php'; ?>
<?php include 'modals/cancel_loa_modal.php'; ?>