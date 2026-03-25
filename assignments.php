<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

/* =========================
   FETCH ASSIGNMENTS
========================= */
$filters = [
    ':branch' => $_GET['branch'] ?? null,
    ':brand' => $_GET['brand'] ?? null,
    ':from_date' => $_GET['from_date'] ?? null,
    ':to_date' => $_GET['to_date'] ?? null
];

$stmt = $pdo->prepare("EXEC get_assignments 
    @branch_name = :branch,
    @brand_name = :brand,
    @from_date = :from_date,
    @to_date = :to_date
");

foreach ($filters as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PHP filter for status (complete/lacking/excess)
$status = $_GET['status'] ?? null;
if($status){
    $assignments = array_filter($assignments, function($a) use($status){
        $shortage = $a['required_count'] - $a['assigned_count'];
        return ($status==='complete' && $shortage===0)
            || ($status==='lacking' && $shortage>0)
            || ($status==='excess' && $shortage<0);
    });
}

// Fetch branches & brands for filter dropdowns
$branches = $pdo->query("SELECT DISTINCT branch_name FROM assignment ORDER BY branch_name")
                ->fetchAll(PDO::FETCH_COLUMN);

$brands = $pdo->query("SELECT DISTINCT brand_name FROM assignment ORDER BY brand_name")
             ->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="content">
    <style>
    .clickable-row { cursor: pointer; transition: background-color 0.2s; }
    .clickable-row:hover { background-color: #f1f1f1; }
    </style>

    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col d-flex justify-content-between align-items-center">
                <h4 class="fw-bold mb-0">Assignments</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlantillaModal">
                    <i class="bi bi-plus-circle"></i> Add Plantilla
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
                            <option value="complete">Complete</option>
                            <option value="lacking">Lacking</option>
                            <option value="excess">Excess</option>
                        </select>
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
                    <table id="assignmentTable" class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Branch</th>
                                <th>Brand</th>
                                <th>Required</th>
                                <th>Assigned</th>
                                <th>Status</th>
                                <th>Updated At</th> <!-- New column -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($assignments as $index => $a): 
                                $shortage = $a['required_count'] - $a['assigned_count'];
                            ?>
                            <tr class="clickable-row">
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($a['branch_name']) ?></td>
                                <td><?= htmlspecialchars($a['brand_name']) ?></td>
                                <td><?= $a['required_count'] ?></td>
                                <td><?= $a['assigned_count'] ?></td>
                                <td data-status="<?= $shortage>0 ? 'lacking' : ($shortage<0 ? 'excess' : 'complete') ?>">
                                    <?php if($shortage>0): ?>
                                        <span class="badge bg-danger">Needs <?= $shortage ?></span>
                                    <?php elseif($shortage<0): ?>
                                        <span class="badge bg-warning">Excess <?= -$shortage ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Complete</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $a['updated_at'] ? date('Y-m-d', strtotime($a['updated_at'])) : '-' ?></td> <!-- Updated At -->
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
    var table = $('#assignmentTable').DataTable({
        pageLength: 10,
        responsive: true,
        dom: 'lrtip'
    });

    $('#filterBranch').on('change', function() {
        table.column(1).search(this.value).draw();
    });
    $('#filterBrand').on('change', function() {
        table.column(2).search(this.value).draw();
    });
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            var statusFilter = $('#filterStatus').val();
            var status = $(table.row(dataIndex).node()).find('td:eq(5)').data('status'); // Status column index = 5

            if (!statusFilter) return true; // no filter, show all
            return status === statusFilter;
        }
    );

    $('#filterStatus').on('change', function() {
        table.draw();
    });

    // DATE RANGE FILTER (custom)
    $.fn.dataTable.ext.search.push(function(settings, data) {
        var from = $('#filterFrom').val();
        var to   = $('#filterTo').val();
        var date = data[0]; // can customize if assignments have a date column

        // No date column? Keep all rows
        return true;
    });

    $('#filterFrom, #filterTo').on('change', function() {
        table.draw();
    });
});
</script>

<?php include 'modals/add_plantilla_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>