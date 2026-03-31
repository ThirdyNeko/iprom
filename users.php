<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);

include 'config/db.php';
include 'auth/require_login.php';

// 🔒 ADMIN ONLY
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

/* =========================
   FETCH USERS
========================= */
$stmt = $pdo->query("SELECT username, role, branch, brand FROM users ORDER BY username ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    #usersTable th,
    #usersTable td {
        text-align: center;
        vertical-align: middle;
    }
</style>

<div class="content">
    <div class="container-fluid">

        <!-- Header -->
        <div class="row mb-3">
            <div class="col d-flex justify-content-between align-items-center">
                <h4 class="fw-bold mb-0">Users</h4>

                <!-- ✅ Create User Button -->
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-plus-circle"></i> Create User
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="card shadow-sm">
            <div class="card-body">

                <div class="table-responsive">
                    <table id="usersTable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Branch</th>
                                <th>Brand</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                    <?php
                                    $roleLabels = [
                                        'admin' => 'ADMIN',
                                        'hr'    => 'HUMAN RESOURCES',
                                    ];
                                    ?>
                                    <td>
                                        <?= isset($roleLabels[$u['role']]) ? $roleLabels[$u['role']] : htmlspecialchars($u['role']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($u['branch'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($u['brand'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- JS (same as promodizers) -->
<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        pageLength: 10,
        responsive: true,
        dom: 'lrtip'
    });
});
</script>
<?php include 'modals/create_user_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>