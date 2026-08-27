<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);

include 'config/db.php';
include 'auth/require_login.php';

// 🔒 Anyone who can manage HR/Branch users OR audit users
if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['super_admin', 'admin', 'supervisor', 'audit_manager', 'audit_supervisor'])
) {
    header("Location: index.php");
    exit;
}

$canSeeHR    = in_array($_SESSION['role'], ['super_admin', 'admin', 'supervisor']);
$canSeeAudit = in_array($_SESSION['role'], ['super_admin', 'audit_manager', 'audit_supervisor']);

// audit_manager / audit_supervisor have no HR access, so Audit is their landing tab
$defaultTab = $canSeeHR ? 'hr' : 'audit';

include 'partials/header.php';
include 'partials/sidebar.php';
?>

<style>
    #usersTable th,
    #usersTable td,
    #usersTableBM th,
    #usersTableBM td,
    #usersTableAudit th,
    #usersTableAudit td {
        text-align: center;
        vertical-align: middle;
        border-right: 1px solid #dee2e6;
    }
    #usersTable th,
    #usersTableBM th,
    #usersTableAudit th {
        background-color: #2d68c4;
        color: white;
    }
    #usersTable th:first-child,
    #usersTable td:first-child,
    #usersTableBM th:first-child,
    #usersTableBM td:first-child,
    #usersTableAudit th:first-child,
    #usersTableAudit td:first-child {
        border-left: 1px solid #dee2e6;
    }
    #usersTable.table-hover tbody tr:hover > td,
    #usersTableBM.table-hover tbody tr:hover > td,
    #usersTableAudit.table-hover tbody tr:hover > td {
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
            <?php if ($canSeeHR): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $defaultTab === 'hr' ? 'active' : '' ?>" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-pane" type="button" role="tab" aria-controls="users-pane" aria-selected="<?= $defaultTab === 'hr' ? 'true' : 'false' ?>">
                    HR
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="branch-managers-tab" data-bs-toggle="tab" data-bs-target="#branch-managers-pane" type="button" role="tab" aria-controls="branch-managers-pane" aria-selected="false">
                    Branch
                </button>
            </li>
            <?php endif; ?>
            <?php if ($canSeeAudit): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $defaultTab === 'audit' ? 'active' : '' ?>" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit-pane" type="button" role="tab" aria-controls="audit-pane" aria-selected="<?= $defaultTab === 'audit' ? 'true' : 'false' ?>">
                    Audit
                </button>
            </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content border border-top-0 rounded-bottom shadow-sm" id="usersTabsContent">

            <?php if ($canSeeHR): ?>
            <!-- USERS TAB -->
            <div class="tab-pane fade <?= $defaultTab === 'hr' ? 'show active' : '' ?> p-0" id="users-pane" role="tabpanel" aria-labelledby="users-tab">
                <div class="card border-0">
                    <div class="card-body pb-0 d-flex justify-content-end">
                        <button class="btn btn-sm btn-success"
                                data-bs-toggle="modal"
                                data-bs-target="#createUserModal"
                                data-role-scope="hr">
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
                                    <!-- populated via DataTables serverSide ajax -->
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
                                    <!-- populated via DataTables serverSide ajax -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canSeeAudit): ?>
            <!-- AUDIT TAB -->
            <div class="tab-pane fade <?= $defaultTab === 'audit' ? 'show active' : '' ?> p-0" id="audit-pane" role="tabpanel" aria-labelledby="audit-tab">
                <div class="card border-0">
                    <div class="card-body pb-0 d-flex justify-content-end">
                        <button class="btn btn-sm btn-success"
                                data-bs-toggle="modal"
                                data-bs-target="#createUserModal"
                                data-role-scope="audit">
                            <i class="bi bi-plus-lg"></i> Add Audit User
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label">Username</label>
                                <div class="clear-input">
                                    <input type="text" id="filterUsernameAudit" class="form-control filter-control" placeholder="Search...">
                                    <button class="clear-btn">&times;</button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select id="filterStatusAudit" class="form-select filter-control">
                                    <option value="">All</option>
                                    <option value="active">ACTIVE</option>
                                    <option value="inactive">INACTIVE</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Position</label>
                                <div class="clear-input">
                                    <input type="text" id="filterPositionAudit" class="form-control filter-control" placeholder="Search...">
                                    <button class="clear-btn">&times;</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table id="usersTableAudit" class="table table-striped table-hover align-middle text-center">
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
                                    <!-- populated via DataTables serverSide ajax -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/users/users.js"></script>

<?php include 'modals/create_user_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
<?php include 'modals/users/user_modal.php'; ?>
</body>
</html>