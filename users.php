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
$stmt = $pdo->prepare("EXEC get_users @role = :role, @branch = :branch, @brand = :brand");
$stmt->execute([
    ':role' => null,
    ':branch' => null,
    ':brand' => null
]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$branches = $pdo->query("SELECT DISTINCT branch_name FROM assignment ORDER BY branch_name")
                ->fetchAll(PDO::FETCH_COLUMN);

$brands = $pdo->query("SELECT DISTINCT brand_name FROM assignment ORDER BY brand_name")
             ->fetchAll(PDO::FETCH_COLUMN);
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
                <div class="row g-2">

                    <div class="col-md-3">
                        <label class="form-label">Role</label>
                        <select id="filterRole" class="form-select">
                            <option value="">All</option>
                            <option value="admin">ADMIN</option>
                            <option value="human resources">HUMAN RESOURCES</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Branch</label>
                        <select id="filterBranch" class="form-select">
                            <option value="">All</option>
                            <?php foreach($branches as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Brand</label>
                        <select id="filterBrand" class="form-select">
                            <option value="">All</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="filterUsername" class="form-control" placeholder="Search...">
                    </div>

                </div>
            </div>
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
    var table = $('#usersTable').DataTable({
        pageLength: 10,
        responsive: true,
        dom: 'lrtip'
    });

    // Filters
    $('#filterRole').on('change', function() {
        var val = this.value;
        table.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
    });
    $('#filterBranch').on('change', function() {
        var val = this.value;
        table.column(2).search(val ? '^' + val + '$' : '', true, false).draw();
    });
    $('#filterBrand').on('change', function() {
        var val = this.value;
        table.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
    });
    $('#filterUsername').on('keyup', function() {
        table.column(0).search(this.value).draw();
    });
});
</script>

<?php include 'modals/create_user_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>