<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

/* ✅ PUT IT HERE (top PHP section) */
$branchMap = [];

$stmt = $pdo->query("
    SELECT branch_code, branch
    FROM IPROM.dbo.branches
    WHERE status = 1
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branchMap[$row['branch_code']] = $row['branch'];
}

$isAllowed =
    (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
    (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');

// Fetch branches & brands for filter dropdowns
$branches = $pdo->query("
    SELECT DISTINCT 
        a.branch_name AS branch_code,
        b.branch AS branch
    FROM assignment a
    LEFT JOIN IPROM.dbo.branches b
        ON a.branch_name = b.branch_code
    ORDER BY b.branch
")->fetchAll(PDO::FETCH_ASSOC);

$brands = $pdo->query("SELECT DISTINCT brand_name FROM assignment ORDER BY brand_name")
             ->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="content">

    <style>
        /* =========================
           TABLE UI FIX (DATA TABLE SAFE)
           ========================= */

        #assignmentTable th,
        #assignmentTable td {
            text-align: center;
            vertical-align: middle;
        }
        #assignmentTable.table-hover tbody tr:hover > td {
            background-color: #e6f0ff !important;
        }

        #assignmentTable th,
        #assignmentTable td {
            border-right: 1px solid #dee2e6;
        }

        #assignmentTable th:first-child,
        #assignmentTable td:first-child {
            border-left: 1px solid #dee2e6; /* remove extra line at start */
        }


        /* ✅ MAKE ROWS CLICKABLE */
        #assignmentTable tbody tr {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        #assignmentTable tbody tr:hover {
            background-color: #f1f1f1;
        }

        #assignmentTable th{
            background-color: #2d68c4;
            color : white;
        }

        .card-body .row.g-2 .col {
            min-width: 160px;
        }

        .filter-control {
            height: 32px !important;
            font-size: 14px;
        }

        .bg-orange {
            background-color: #ffd700 !important;
            color: #fff !important;
        }
    </style>

    <div class="container-fluid">
        <div class="row mb-3 align-items-center">

            <!-- TITLE -->
            <div class="col-md-6">
                <h4 class="fw-bold mb-0">Assignment Overview</h4>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="col-md-6 text-md-end mt-2 mt-md-0">

                <div class="d-flex justify-content-md-end gap-2 flex-wrap">

                    <button id="exportExcel" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </button>
                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin') || ($_SESSION['role'] === 'super_admin')): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlantillaModal" <?= !$isAllowed ? 'disabled' : '' ?>>
                        <i class="bi bi-plus-circle" ></i> Create Plantilla
                    </button>
                    <?php endif; ?>
                </div>

            </div>

        </div>

        <!-- Table -->
        <div class="card shadow-sm">
            <div class="card-body">

                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label">Branch</label>
                        <select id="filterBranch" class="form-select filter-control">
                            <option value="">All</option>
                            <?php 
                            $sessionBranches = !empty($_SESSION['branch']) 
                                ? array_map('trim', explode(',', $_SESSION['branch'])) 
                                : [];

                            foreach($branches as $b): 
                                // If user has branch restrictions, only show their branches
                                if (!empty($sessionBranches) && !in_array($b['branch_code'], $sessionBranches)) continue;
                            ?>
                                <option value="<?= htmlspecialchars($b['branch_code']) ?>">
                                    <?= htmlspecialchars($b['branch']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Brand</label>
                        <select id="filterBrand" class="form-select filter-control">
                            <option value="">All</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>">
                                    <?= htmlspecialchars($b) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select id="filterStatus" class="form-select filter-control">
                            <option value="">All</option>
                            <option value="complete">COMPLETE</option>
                            <option value="lacking">INCOMPLETE</option>
                            <option value="zero">VACANT</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" id="filterFrom" class="form-control filter-control">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" id="filterTo" class="form-control filter-control">
                    </div>
                </div>

            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table id="assignmentTable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th>Branch</th>
                                <th>Brand</th>
                                <th>Plantilla Count</th>
                                <th>Assigned</th>
                                <th>Status</th>
                                <th>Date Last Updated</th>
                                <th>Last Updated By</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- JS -->

<script>
const branchMap = <?= json_encode($branchMap) ?>;
const sessionBranches = <?= json_encode($sessionBranches) ?>;
const isStaff         = <?= json_encode(isset($_SESSION['role']) && $_SESSION['role'] === 'staff') ?>;
</script>
<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/xlsx.full.min.js"></script>
<script src="assets/js/assignment/assignments.js"></script>

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