<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
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
        #assignmentTable th,
        #assignmentTable td {
            text-align: center;
            vertical-align: middle;
        }
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
                            <option value="complete">ACTIVE</option>
                            <option value="lacking">VACANT</option>
                            <option value="zero">INACTIVE</option>
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
                    <table id="assignmentTable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-dark">
                            <tr>
                                <th>Branch</th>
                                <th>Brand</th>
                                <th>Required</th>
                                <th>Assigned</th>
                                <th>Status</th>
                                <th>Last Updated At</th>
                                <th>Last Updated By</th>
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
<script src="assets/js/assignments.js"></script>

<?php include 'modals/assignment_modal.php'; ?>
<?php include 'modals/edit_promodizer_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
<?php include 'modals/add_plantilla_modal.php'; ?>

<div class="modal fade" id="editPromodizerModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-body" id="editPromodizerContent">
        <div class="text-center py-3">
          <div class="spinner-border text-primary"></div>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>