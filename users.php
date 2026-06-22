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

$visibleRoles = match($_SESSION['role']) {
    'super_admin' => ['admin', 'supervisor', 'staff'],
    'admin'       => ['supervisor', 'staff'],
    'supervisor'  => ['staff'],
    default       => []
};

$users = array_filter($users, fn($u) => in_array($u['role'], $visibleRoles));
?>

<style>
    #usersTable th,
    #usersTable td {
        text-align: center;
        vertical-align: middle;
    }
    #usersTable th,
    #usersTable td {
        border-right: 1px solid #dee2e6;
    }
    #usersTable th {
        background-color: #2d68c4;
        color: white;
    }
    #usersTable th:first-child,
    #usersTable td:first-child {
        border-left: 1px solid #dee2e6;
    }
    #usersTable.table-hover tbody tr:hover > td {
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
</style>

<div class="content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col d-flex justify-content-between align-items-center">
                <h4 class="fw-bold mb-0">Users</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-plus-circle"></i> Create User
                </button>
            </div>
        </div>

        <div class="card shadow-sm">
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
            <div class="card-body">
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
                            <?php
                            $roleLabels = [
                                'admin'       => 'ADMIN',
                                'super_admin' => 'SUPER ADMIN',
                                'staff'       => 'STAFF',
                                'supervisor'  => 'SUPERVISOR',
                            ];
                            foreach ($users as $u):
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
</div>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function () {
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
    $('.clear-btn').on('click', function () {
        $(this).siblings('input').val('').trigger('keyup');
    });
});

/* ───────────────────────────────────────────
   USER STATUS SWITCH
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
            // revert toggle
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
                    // update badge next to the switch
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