<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);

include 'config/db.php';
include 'auth/require_login.php';

// 🔒 ADMIN ONLY
if (
    !isset($_SESSION['role']) ||
    ($_SESSION['role'] !== 'super_admin' &&
     $_SESSION['role'] !== 'admin' &&
     $_SESSION['role'] !== 'supervisor')
) {
    header("Location: index.php");
    exit;
}

include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

/* =========================
   FETCH USERS
========================= */
$users = $pdo
    ->query("EXEC get_users @role = NULL")
    ->fetchAll(PDO::FETCH_ASSOC);

// NOTE: assumes get_users returns a `branch` column (comma-delimited
// branch codes) same as the rest of the app relies on elsewhere.
// Branch Managers only ever have a single code in that field.

$visibleRoles = match($_SESSION['role']) {
    'super_admin' => ['admin', 'supervisor', 'staff', 'branch_manager'],
    'admin'       => ['supervisor', 'staff', 'branch_manager'],
    'supervisor'  => ['staff'],
    default       => []
};

$hiddenUsernames = ['QA_HR_ADMIN', 'QA_HR_SUPERVISOR', 'QA_HR_STAFF'];
$excludeUsernames = in_array($_SESSION['role'], ['admin', 'supervisor'])
    ? $hiddenUsernames
    : [];

$users = array_filter($users, fn($u) =>
    in_array($u['role'], $visibleRoles) &&
    !in_array($u['username'], $excludeUsernames)
);

/* =========================
   SPLIT: USERS vs BRANCH MANAGERS
========================= */
$regularUsers   = array_filter($users, fn($u) => $u['role'] !== 'branch_manager');
$branchManagers = array_filter($users, fn($u) => $u['role'] === 'branch_manager');

/* =========================
   BRANCH CODE -> NAME MAP
   (needed to display the single branch for Branch Managers)
========================= */
$branchNameMap = [];
try {
    $stmt = $pdo->prepare("EXEC dbo.get_branches_brands @branch = NULL");
    $stmt->execute();
    $branchRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($branchRows as $b) {
        $branchNameMap[$b['branch_code']] = $b['branch'];
    }
} catch (PDOException $e) {
    $branchNameMap = [];
}

function bm_branch_name(array $u, array $map): string {
    if (empty($u['branch'])) return '-';
    $code = trim(explode(',', $u['branch'])[0]);
    return $map[$code] ?? $code;
}

$roleLabels = [
    'admin'          => 'ADMIN',
    'super_admin'    => 'SUPER ADMIN',
    'staff'          => 'STAFF',
    'supervisor'     => 'SUPERVISOR',
    'branch_manager' => 'BRANCH MANAGER',
];
?>

<style>
    #usersTable th,
    #usersTable td,
    #usersTableBM th,
    #usersTableBM td {
        text-align: center;
        vertical-align: middle;
        border-right: 1px solid #dee2e6;
    }
    #usersTable th,
    #usersTableBM th {
        background-color: #2d68c4;
        color: white;
    }
    #usersTable th:first-child,
    #usersTable td:first-child,
    #usersTableBM th:first-child,
    #usersTableBM td:first-child {
        border-left: 1px solid #dee2e6;
    }
    #usersTable.table-hover tbody tr:hover > td,
    #usersTableBM.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
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
    .nav-tabs .nav-link.active {
        font-weight: 600;
        color: #2d68c4;
    }
</style>

<div class="content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col">
                <h4 class="fw-bold mb-0">Users</h4>
            </div>
        </div>

        <ul class="nav nav-tabs" id="usersTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-pane" type="button" role="tab" aria-controls="users-pane" aria-selected="true">
                    Head Office
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="branch-managers-tab" data-bs-toggle="tab" data-bs-target="#branch-managers-pane" type="button" role="tab" aria-controls="branch-managers-pane" aria-selected="false">
                    Branch
                </button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 rounded-bottom shadow-sm" id="usersTabsContent">

            <!-- USERS TAB -->
            <div class="tab-pane fade show active p-0" id="users-pane" role="tabpanel" aria-labelledby="users-tab">
                <div class="card border-0">
                    <div class="card-body pb-0 d-flex justify-content-end">
                        <button class="btn btn-sm btn-success"
                                data-bs-toggle="modal"
                                data-bs-target="#createUserModal">
                            <i class="bi bi-plus-lg"></i> Add User
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label">Username</label>
                                <div class="clear-input">
                                    <input type="text" id="filterUsername" class="form-control filter-control" placeholder="Search...">
                                    <button class="clear-btn">&times;</button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select id="filterStatus" class="form-select filter-control">
                                    <option value="">All</option>
                                    <option value="active">ACTIVE</option>
                                    <option value="inactive">INACTIVE</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Position</label>
                                <div class="clear-input">
                                    <input type="text" id="filterPosition" class="form-control filter-control" placeholder="Search...">
                                    <button class="clear-btn">&times;</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table id="usersTable" class="table table-striped table-hover align-middle text-center">
                                <thead class="table-primary text-center">
                                    <tr>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($regularUsers as $u):
                                        $isActive = strtolower($u['status']) === 'active';
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($u['username']) ?></td>
                                            <td><?= $roleLabels[$u['role']] ?? htmlspecialchars($u['role']) ?></td>
                                            <td><?= htmlspecialchars($u['position'] ?? '-') ?></td>
                                            <td data-search="<?= $isActive ? 'active' : 'inactive' ?>">
                                                <div class="d-flex align-items-center justify-content-center gap-2">
                                                    <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                    <div class="form-check form-switch m-0">
                                                        <input class="form-check-input user-status-switch"
                                                               type="checkbox"
                                                               data-username="<?= htmlspecialchars($u['username']) ?>"
                                                               <?= $isActive ? 'checked' : '' ?>>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-success view-user"
                                                        data-username="<?= htmlspecialchars($u['username']) ?>">
                                                    Update
                                                </button>
                                                <button class="btn btn-sm btn-primary view-user view-user-readonly"
                                                        data-username="<?= htmlspecialchars($u['username']) ?>">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BRANCH MANAGERS TAB -->
            <div class="tab-pane fade p-0" id="branch-managers-pane" role="tabpanel" aria-labelledby="branch-managers-tab">
                <div class="card border-0">
                    <div class="card-body pb-0 d-flex justify-content-end">
                        <button class="btn btn-sm btn-success"
                                data-bs-toggle="modal"
                                data-bs-target="#createUserModal"
                                data-preset-role="branch_manager">
                            <i class="bi bi-plus-lg"></i> Add Branch User
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label">Username</label>
                                <div class="clear-input">
                                    <input type="text" id="filterUsernameBM" class="form-control filter-control" placeholder="Search...">
                                    <button class="clear-btn">&times;</button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select id="filterStatusBM" class="form-select filter-control">
                                    <option value="">All</option>
                                    <option value="active">ACTIVE</option>
                                    <option value="inactive">INACTIVE</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Branch</label>
                                <div class="clear-input">
                                    <input type="text" id="filterBranchBM" class="form-control filter-control" placeholder="Search...">
                                    <button class="clear-btn">&times;</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table id="usersTableBM" class="table table-striped table-hover align-middle text-center">
                                <thead class="table-primary text-center">
                                    <tr>
                                        <th>Username</th>
                                        <th>Branch</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($branchManagers as $u):
                                        $isActive = strtolower($u['status']) === 'active';
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($u['username']) ?></td>
                                            <td><?= htmlspecialchars(bm_branch_name($u, $branchNameMap)) ?></td>
                                            <td><?= htmlspecialchars($u['position'] ?? '-') ?></td>
                                            <td data-search="<?= $isActive ? 'active' : 'inactive' ?>">
                                                <div class="d-flex align-items-center justify-content-center gap-2">
                                                    <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                    <div class="form-check form-switch m-0">
                                                        <input class="form-check-input user-status-switch"
                                                               type="checkbox"
                                                               data-username="<?= htmlspecialchars($u['username']) ?>"
                                                               <?= $isActive ? 'checked' : '' ?>>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-success view-user"
                                                        data-username="<?= htmlspecialchars($u['username']) ?>">
                                                    Update
                                                </button>
                                                <button class="btn btn-sm btn-primary view-user view-user-readonly"
                                                        data-username="<?= htmlspecialchars($u['username']) ?>">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function () {

    /* ── USERS TABLE ── */
    var table = $('#usersTable').DataTable({
        pageLength: 25,
        responsive: true,
        dom: 'lrtip',
        language: {
            emptyTable: "No data available",
            zeroRecords: "No Users match the selected filters",
        },
    });

    $('#filterStatus').on('change', function () {
        var val = this.value;
        table.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
    });
    $('#filterPosition').on('keyup', function () {
        table.column(2).search(this.value).draw();
    });
    $('#filterUsername').on('keyup', function () {
        table.column(0).search(this.value).draw();
    });

    /* ── BRANCH MANAGERS TABLE ── */
    var tableBM = $('#usersTableBM').DataTable({
        pageLength: 25,
        responsive: true,
        dom: 'lrtip',
        language: {
            emptyTable: "No data available",
            zeroRecords: "No Branch Managers match the selected filters",
        },
    });

    $('#filterStatusBM').on('change', function () {
        var val = this.value;
        tableBM.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
    });
    $('#filterBranchBM').on('keyup', function () {
        tableBM.column(1).search(this.value).draw();
    });
    $('#filterUsernameBM').on('keyup', function () {
        tableBM.column(0).search(this.value).draw();
    });

    // recalc column widths once the Branch Managers tab is actually shown,
    // since DataTables mis-measures hidden panes on init
    $('#branch-managers-tab').on('shown.bs.tab', function () {
        tableBM.columns.adjust();
    });

    $('.clear-btn').on('click', function () {
        $(this).siblings('input').val('').trigger('keyup');
    });
});

/* ───────────────────────────────────────────
   USER STATUS SWITCH (delegated — works for both tables)
─────────────────────────────────────────── */
$(document).on('change', '.user-status-switch', function () {
    const toggle    = $(this);
    const username  = toggle.data('username');
    const newStatus = toggle.is(':checked') ? 'ACTIVE' : 'INACTIVE';
    const isEnable  = newStatus === 'ACTIVE';

    toggle.prop('disabled', true);

    Swal.fire({
        icon: isEnable ? 'question' : 'warning',
        title: `${isEnable ? 'Enable' : 'Disable'} User?`,
        html: `This will set <strong>${username}</strong> to <strong>${newStatus}</strong>.`,
        showCancelButton: true,
        confirmButtonText: 'Yes',
        confirmButtonColor: isEnable ? '#198754' : '#dc3545',
    }).then((result) => {
        if (!result.isConfirmed) {
            toggle.prop('checked', !toggle.is(':checked'));
            toggle.prop('disabled', false);
            return;
        }

        $.ajax({
            url: 'functions/update_user_status.php',
            type: 'POST',
            data: { username, status: newStatus },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    const $badge = toggle.closest('td').find('.badge');
                    $badge
                        .text(isEnable ? 'Active' : 'Inactive')
                        .removeClass('bg-success bg-secondary')
                        .addClass(isEnable ? 'bg-success' : 'bg-secondary');

                    Swal.fire({
                        icon: 'success',
                        title: `User ${newStatus === 'ACTIVE' ? 'Enabled' : 'Disabled'}`,
                        text: `${username} is now ${newStatus}.`,
                        timer: 1500,
                        showConfirmButton: false,
                    });
                } else {
                    toggle.prop('checked', !toggle.is(':checked'));
                    Swal.fire('Error', res.message || 'Failed to update status.', 'error');
                }
            },
            error: function () {
                toggle.prop('checked', !toggle.is(':checked'));
                Swal.fire('Error', 'Request failed.', 'error');
            },
            complete: function () {
                toggle.prop('disabled', false);
            },
        });
    });
});
</script>

<?php include 'modals/create_user_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
<?php include 'modals/users/user_modal.php'; ?>
</body>
</html>