<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

/* =========================
   FETCH PROMODIZERS
========================= */
// Call SP without any parameters
$filters = [
    ':branch' => $_GET['branch'] ?? null,
    ':brand' => $_GET['brand'] ?? null,
    ':status' => $_GET['status'] ?? null,
    ':assigned_by' => $_GET['assigned_by'] ?? null,
    ':from_date' => $_GET['from_date'] ?? null,
    ':to_date' => $_GET['to_date'] ?? null
];

$stmt = $pdo->prepare("EXEC get_promodizers 
    @branch = :branch,
    @brand = :brand,
    @status = :status,
    @assigned_by = :assigned_by,
    @from_date = :from_date,
    @to_date = :to_date
");

foreach ($filters as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$promodizers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col d-flex justify-content-between align-items-center">
                <h4 class="fw-bold mb-0">Promodizers</h4>

                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                    <i class="bi bi-plus-circle"></i> Add Employee
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="promodizerTable" class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Branch</th>
                                <th>Brand</th>
                                <th>Status</th>
                                <th>Last Assigned By</th>
                                <th>Assignment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($promodizers as $index => $p): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= $p['first_name'] ?> <?= $p['last_name'] ?></td>
                                    <td><?= $p['branch'] ?? '-' ?></td>
                                    <td><?= $p['brand'] ?? '-' ?></td>
                                    <td><?= $p['status'] ?? '-' ?></td>
                                    <td><?= $p['last_assigned_by'] ?? '-' ?></td>
                                    <td><?= $p['assignment_date'] ? date('Y-m-d', strtotime($p['assignment_date'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- JS -->
<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    $('#promodizerTable').DataTable({
        "pageLength": 10,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true
    });
});
</script>
<?php include 'modals/add_employee_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>