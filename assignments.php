<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

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
            </div>
        </div>

        <!-- Filters -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label">Branch</label>
                        <select id="filterBranch" class="form-select">
                            <option value="">All</option>
                            <?php foreach($branches as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Brand</label>
                        <select id="filterBrand" class="form-select">
                            <option value="">All</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
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
        </div>

        <!-- Table -->
        <div class="card shadow-sm">
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
                                <th>Updated At</th>
                            </tr>
                        </thead>
                        <tbody></tbody> <!-- Server-side AJAX will populate -->
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
        processing: true,
        serverSide: true,
        ajax: {
            url: 'functions/fetch_assignments.php',
            type: 'POST',
            data: function(d) {
                d.branch = $('#filterBranch').val();
                d.brand  = $('#filterBrand').val();
                d.status = $('#filterStatus').val();
                d.from_date = $('#filterFrom').val();
                d.to_date   = $('#filterTo').val();
            }
        },
        pageLength: 50,
        lengthMenu: [10,25,50,100],
        responsive: true,
        dom: 'lrtip',
        order: [[6,'desc']]
    });

    // Reload table on filter change
    $('#filterBranch,#filterBrand,#filterStatus,#filterFrom,#filterTo').on('change', function(){
        table.ajax.reload();
    });

    // Clickable row handler
    $('#assignmentTable tbody').on('click', 'tr.clickable-row', function() {
        var branch = $(this).data('branch');
        var brand  = $(this).data('brand');
        var required = $(this).data('required');
        var assigned = $(this).data('assigned');
        var updated = $(this).data('updated');

    });

});
</script>

<?php include 'modals/assignment_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>