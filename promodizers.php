<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
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
// Fetch branches & brands
$branches = $pdo->query("SELECT DISTINCT branch_name FROM assignment ORDER BY branch_name")
                ->fetchAll(PDO::FETCH_COLUMN);

$brands = $pdo->query("SELECT DISTINCT brand_name FROM assignment ORDER BY brand_name")
             ->fetchAll(PDO::FETCH_COLUMN);

// Include modal

?>

<div class="content">
    <style>
    .clickable-row {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .clickable-row:hover {
        background-color: #f1f1f1; /* light gray on hover */
    }
    #promodizerTable th,
    #promodizerTable td {
        text-align: center;
        vertical-align: middle;
    }
    </style>
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
                <div class="row g-2">

                    <div class="col-md-2">
                        <label class="form-label">Branch</label>
                        <select id="filterBranch" class="form-select">
                            <option value="">All</option>
                            <?php foreach($branches as $b): ?>
                                <option value="<?= $b ?>"><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Brand</label>
                        <select id="filterBrand" class="form-select">
                            <option value="">All</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= $b ?>"><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select id="filterStatus" class="form-select">
                            <option value="">All</option>
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                            <option value="TERMINATED">TERMINATED</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Assigned By</label>
                        <input type="text" id="filterAssignedBy" class="form-control" placeholder="Search...">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" id="filterFrom" class="form-control">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" id="filterTo" class="form-control">
                    </div>

                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="promodizerTable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-dark">
                            <tr>
                                <th>Name</th>
                                <th>Branch</th>
                                <th>Brand</th>
                                <th>Status</th>
                                <th>Last Assigned By</th>
                                <th>Assignment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($promodizers as $p): ?>
                                <tr class="clickable-row" 
                                    data-id="<?= $p['id'] ?>" 
                                    data-branch="<?= htmlspecialchars($p['branch']) ?>" 
                                    data-brand="<?= htmlspecialchars($p['brand']) ?>">
                                    <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                                    <td><?= htmlspecialchars($p['branch'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['brand'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['status'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['last_assigned_by'] ?? '-') ?></td>
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
    var table = $('#promodizerTable').DataTable({
        pageLength: 10,
        responsive: true,
        dom: 'lrtip'
    });

    // Column indexes after removing ID column:
    // 0 Name, 1 Branch, 2 Brand, 3 Status, 4 Assigned By, 5 Date

    $('#filterBranch').on('change', function() {
        var val = this.value;
        table.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
    });

    $('#filterBrand').on('change', function() {
        var val = this.value;
        table.column(2).search(val ? '^' + val + '$' : '', true, false).draw();
    });
    
    $('#filterStatus').on('change', function() {
        var val = this.value;
        table.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
    });

    $('#filterAssignedBy').on('keyup', function() {
        table.column(4).search(this.value).draw();
    });

    // DATE RANGE FILTER (custom)
    $.fn.dataTable.ext.search.push(function(settings, data) {
        var from = $('#filterFrom').val();
        var to   = $('#filterTo').val();
        var date = data[5]; // Assignment Date column

        if (!date) return true;

        var rowDate = new Date(date);
        var fromDate = from ? new Date(from) : null;
        var toDate   = to ? new Date(to) : null;

        if (
            (!fromDate || rowDate >= fromDate) &&
            (!toDate || rowDate <= toDate)
        ) {
            return true;
        }
        return false;
    });

    $('#filterFrom, #filterTo').on('change', function() {
        table.draw();
    });
});
</script>
<?php include 'modals/edit_promodizer_modal.php'; ?>
<?php include 'modals/add_employee_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>