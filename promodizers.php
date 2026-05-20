<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

$branchMap = [];

$stmt = $pdo->query("
    SELECT branch_code, branch
    FROM IPROM.dbo.branches
    WHERE status = 1
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branchMap[$row['branch_code']] = $row['branch'];
}



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
// Fetch branches & brands for filter dropdowns
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
    .clickable-row {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    #promodizerTable.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }
    #promodizerTable th,
    #promodizerTable td {
        text-align: center;
        vertical-align: middle;
    }
    #promodizerTable td:first-child {
        text-align: left !important;
    }

    #promodizerTable th,
    #promodizerTable td {
        border-right: 1px solid #dee2e6;
    }

    #promodizerTable th:first-child,
    #promodizerTable td:first-child {
        border-left: 1px solid #dee2e6; /* remove extra line at start */
    }
    .card-body .col {
        min-width: 150px;
    }

    .card-body .row.g-2 .col {
        min-width: 160px;
    }

    .filter-control {
        height: 32px !important;
        font-size: 14px;
    }

    #promodizerTable th{
        background-color: #2d68c4;
        color : white;
    }

    .clear-input {
        position: relative;
    }

    .clear-input input {
        padding-right: 28px; /* space for X */
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
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col d-flex justify-content-between align-items-center">
                <h4 class="fw-bold mb-0">Promodiser Overview</h4>

                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                    <i class="bi bi-plus-circle"></i> Add Employee
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-2">

                    <div class="col">
                        <label class="form-label">Name</label>
                        <div class="clear-input">
                            <input type="text" id="filterName" class="form-control form-control-sm filter-control" placeholder="Search...">
                            <button type="button" class="clear-btn" data-target="filterName">×</button>
                        </div>
                    </div>

                    <div class="col">
                        <label class="form-label">Branch</label>
                        <select id="filterBranch" class="form-select filter-control">
                            <option value="">All</option>
                            <?php foreach($branches as $b): ?>
                                <option value="<?= htmlspecialchars($b['branch_code']) ?>">
                                    <?= htmlspecialchars($b['branch']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col">
                        <label class="form-label">Brand</label>
                        <select id="filterBrand" class="form-select filter-control">
                            <option value="">All</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= $b ?>"><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col">
                        <label class="form-label">Status</label>
                        <select id="filterStatus" class="form-select filter-control">
                            <option value="">All</option>
                            <option value="ACTIVE" selected>ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                    </div>

                    <div class="col">
                        <label class="form-label">Employment Status</label>
                        <select id="filterEmploymentStatus" class="form-select filter-control">
                            <option value="">All</option>
                            <option value="PERMANENT">PERMANENT</option>
                            <option value="SEASONAL">SEASONAL</option>
                            <option value="RELIEVER">RELIEVER</option>
                        </select>
                    </div>

                    <div class="col">
                        <label class="form-label">Sub Status</label>
                        <select id="filterSubStatus" class="form-select filter-control">
                            <option value="">All</option>
                            <option value="STATIONARY">STATIONARY</option>
                            <option value="MULTI BRANCH">MULTI BRANCH</option>
                            <option value="MULTI BRAND">MULTI BRAND</option>
                        </select>
                    </div>

                    <div class="col">
                        <label class="form-label">Assigned By</label>
                        <div class="clear-input">
                            <input type="text" id="filterAssignedBy" class="form-control form-control-sm filter-control" placeholder="Search...">
                            <button type="button" class="clear-btn" data-target="filterAssignedBy">×</button>
                        </div>
                    </div>

                    <div class="col">
                        <label class="form-label">From</label>
                        <input type="date" id="filterFrom" class="form-control filter-control">
                    </div>

                    <div class="col">
                        <label class="form-label">To</label>
                        <input type="date" id="filterTo" class="form-control filter-control">
                    </div>

                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="promodizerTable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th>Name</th>
                                <th>Branch</th>
                                <th>Brand</th>
                                <th>Status</th>
                                <th>Employment Status</th> <!-- NEW -->
                                <th>Sub-Status</th> <!-- NEW -->
                                <th>Assignment Date</th>
                                <th>Last Assigned By</th>                                
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($promodizers as $p): ?>
                                <tr class="clickable-row" 
                                    data-id="<?= $p['id'] ?>" 
                                    data-branch="<?= htmlspecialchars($p['branch']) ?>" 
                                    data-brand="<?= htmlspecialchars($p['brand']) ?>">
                                    
                                    <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                                    <td data-branch="<?= htmlspecialchars($p['branch']) ?>">
                                        <?= htmlspecialchars($branchMap[$p['branch']] ?? '-') ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['brand'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['status'] ?? '-') ?></td>

                                    <!-- NEW -->
                                    <td><?= htmlspecialchars($p['employment_status'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['sub_status'] ?? '-') ?></td>

                                    <td><?= $p['assignment_date'] ? date('Y-m-d', strtotime($p['assignment_date'])) : '-' ?></td>
                                    <td><?= htmlspecialchars($p['last_assigned_by'] ?? '-') ?></td>                                    
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
<script>
document.querySelectorAll(".clear-btn").forEach(btn => {
  btn.addEventListener("click", () => {

    const targetId = btn.getAttribute("data-target");
    const input = document.getElementById(targetId);

    input.value = "";

    // trigger DataTable refresh
    input.dispatchEvent(new Event("input"));
  });
});
</script>
<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/employee/promodizers.js"></script>
<?php include 'modals/edit_promodizer_modal.php'; ?>
<?php include 'modals/add_employee_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>