<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

// ✅ Define once, use everywhere
$sessionBranches = !empty($_SESSION['branch'])
    ? array_map('trim', explode(',', $_SESSION['branch']))
    : [];

$branchMap = [];

$stmt = $pdo->query("
    SELECT branch_code, branch, area, region, corpo
    FROM IPROM.dbo.branches                          
    WHERE status = 1
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branchMap[$row['branch_code']] = [
        'branch' => $row['branch'],
        'area'   => $row['area'],
        'region' => $row['region'],
        'corpo'  => $row['corpo'],   // ← add this
    ];
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
    ':to_date' => $_GET['to_date'] ?? null,

    ':employment_status' => $_GET['employment_status'] ?? null,
    ':sub_status' => $_GET['sub_status'] ?? null,
    ':search' => $_GET['search'] ?? null,
    ':corpo' => $_GET['corpo'] ?? null,
    ':agency' => $_GET['agency'] ?? null
];

$stmt = $pdo->prepare("EXEC get_promodizers 
    @branch = :branch,
    @brand = :brand,
    @status = :status,
    @assigned_by = :assigned_by,
    @from_date = :from_date,
    @to_date = :to_date,
    @employment_status = :employment_status,
    @sub_status = :sub_status,
    @search = :search,
    @corpo = :corpo,
    @agency = :agency
");

foreach ($filters as $key => $value) {
    $stmt->bindValue($key, $value);
}

$branches = $pdo->query("
    SELECT DISTINCT 
        a.branch_name AS branch_code,
        b.branch AS branch
    FROM assignment a
    LEFT JOIN IPROM.dbo.branches b
        ON a.branch_name = b.branch_code
    ORDER BY b.branch
")->fetchAll(PDO::FETCH_ASSOC);

$stmt->execute();
$promodizers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Restrict to session branches
$sessionBranches = !empty($_SESSION['branch'])
    ? array_map('trim', explode(',', $_SESSION['branch']))
    : [];

$isStaff = isset($_SESSION['role']) && $_SESSION['role'] === 'staff';

if ($isStaff) {
    $promodizers = empty($sessionBranches)
        ? []
        : array_values(array_filter(
            $promodizers,
            fn($p) => in_array(trim($p['branch']), $sessionBranches)
        ));
}
// else: non-staff sees everything — no filtering
// Fetch branches & brands for filter dropdowns

$brands = $pdo->query("SELECT DISTINCT brand_name FROM assignment ORDER BY brand_name")
             ->fetchAll(PDO::FETCH_COLUMN);

$regions = $pdo->query("SELECT DISTINCT region FROM branches ORDER BY region")
             ->fetchAll(PDO::FETCH_COLUMN);

$areas = $pdo->query("SELECT DISTINCT area FROM branches ORDER BY area")
             ->fetchAll(PDO::FETCH_COLUMN);

$corpos = $pdo->query("SELECT DISTINCT corpo FROM branches ORDER BY corpo")
             ->fetchAll(PDO::FETCH_COLUMN);

$agencies = $pdo->query("SELECT DISTINCT agencies FROM agencies ORDER BY agencies")
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
    /* Change this */
    #promodizerTable td:first-child {
        text-align: left !important;
    }

    /* To this */
    #promodizerTable tbody tr td[colspan] {
        text-align: center !important;
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
        <div class="row mb-3 align-items-center">

            <!-- TITLE -->
            <div class="col-md-6">
                <h4 class="fw-bold mb-0">Promodiser Overview</h4>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="col-md-6 text-md-end mt-2 mt-md-0">

                <div class="d-flex justify-content-md-end gap-2 flex-wrap">

                    <button id="exportExcel" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </button>

                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                        <i class="bi bi-plus-circle"></i> Add Employee
                    </button>

                </div>

            </div>

        </div>

        <!-- Table -->
        <div class="card shadow-sm">
            <!-- CARD BODY -->
            <div class="card-body">
                <div class="row g-2 align-items-end">

                    <!-- NAME SEARCH -->
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <div class="clear-input">
                            <input type="text" id="filterName"
                                class="form-control form-control-sm filter-control"
                                placeholder="Search...">
                            <button type="button" class="clear-btn" data-target="filterName">×</button>
                        </div>
                    </div>

                    <!-- ADVANCE SEARCH BUTTON -->
                    <div class="col-auto">
                        <button type="button"
                            class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#advanceSearchModal">
                            Advance Search
                        </button>
                    </div>

                </div>
            </div>

            <!-- ADVANCE SEARCH MODAL -->
            <div class="modal fade" id="advanceSearchModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">

                        <!-- HEADER -->
                        <div class="modal-header">
                            <h5 class="modal-title">Advance Search</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <!-- BODY -->
                        <div class="modal-body">

                            <div class="row g-3">

                                <!-- BRANCH -->
                                <div class="col-md-4">
                                    <label class="form-label">Branch</label>
                                    <select id="filterBranch" class="form-select filter-control">
                                        <option value="">All</option>
                                        <?php 
                                        $sessionBranches = !empty($_SESSION['branch']) 
                                            ? array_map('trim', explode(',', $_SESSION['branch'])) 
                                            : [];

                                        foreach($branches as $b): 
                                            if (!empty($sessionBranches) && !in_array($b['branch_code'], $sessionBranches)) continue;
                                        ?>
                                            <option value="<?= htmlspecialchars($b['branch_code']) ?>">
                                                <?= htmlspecialchars($b['branch']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- BRAND -->
                                <div class="col-md-4">
                                    <label class="form-label">Brand</label>
                                    <select id="filterBrand" class="form-select filter-control">
                                        <option value="">All</option>
                                        <?php foreach($brands as $b): ?>
                                            <option value="<?= $b ?>"><?= $b ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- STATUS -->
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select id="filterStatus" class="form-select filter-control">
                                        <option value="">All</option>
                                        <option value="ACTIVE" selected>ACTIVE</option>
                                        <option value="INACTIVE">INACTIVE</option>
                                    </select>
                                </div>

                                <!-- EMPLOYMENT STATUS -->
                                <div class="col-md-4">
                                    <label class="form-label">Employment Status</label>
                                    <select id="filterEmploymentStatus" class="form-select filter-control">
                                        <option value="">All</option>
                                        <option value="PERMANENT">PERMANENT</option>
                                        <option value="SEASONAL">SEASONAL</option>
                                        <option value="RELIEVER">RELIEVER</option>
                                    </select>
                                </div>

                                <!-- SUB STATUS -->
                                <div class="col-md-4">
                                    <label class="form-label">Sub Status</label>
                                    <select id="filterSubStatus" class="form-select filter-control">
                                        <option value="">All</option>
                                        <option value="STATIONARY">STATIONARY</option>
                                        <option value="MULTI BRANCH">MULTI BRANCH</option>
                                        <option value="MULTI BRAND">MULTI BRAND</option>
                                        <option value="HYBRID">HYBRID</option>
                                    </select>
                                </div>

                                <!-- ASSIGNED BY -->
                                <div class="col-md-4">
                                    <label class="form-label">Assigned By</label>
                                    <div class="clear-input">
                                        <input type="text" id="filterAssignedBy"
                                            class="form-control form-control-sm filter-control"
                                            placeholder="Search...">
                                        <button type="button" class="clear-btn" data-target="filterAssignedBy">×</button>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Region</label>
                                    <select id="filterRegion" class="form-select filter-control">
                                        <option value="">All</option>
                                        <?php foreach($regions as $r): ?>
                                            <option value="<?= $r ?>"><?= $r ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Area</label>
                                    <select id="filterArea" class="form-select filter-control">
                                        <option value="">All</option>
                                        <?php foreach($areas as $a): ?>
                                            <option value="<?= $a ?>"><?= $a ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Company</label>
                                    <select id="filterCompany" class="form-select filter-control">
                                        <option value="">All</option>
                                        <?php foreach($corpos as $c): ?>
                                            <option value="<?= htmlspecialchars($c) ?>">
                                                <?= strtoupper(htmlspecialchars($c)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Agency</label>
                                    <select id="filterAgency" class="form-select filter-control">
                                        <option value="">All</option>
                                        <?php foreach($agencies as $ag): ?>
                                            <option value="<?= $ag ?>"><?= $ag ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- FROM -->
                                <div class="col-md-6">
                                    <label class="form-label">From</label>
                                    <input type="date" id="filterFrom" class="form-control filter-control">
                                </div>

                                <!-- TO -->
                                <div class="col-md-6">
                                    <label class="form-label">To</label>
                                    <input type="date" id="filterTo" class="form-control filter-control">
                                </div>

                            </div>

                        </div>

                        <!-- FOOTER -->
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm"
                                data-bs-dismiss="modal">
                                Close
                            </button>
                        </div>

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
                                    data-brand="<?= htmlspecialchars($p['brand']) ?>"
                                    data-company="<?= htmlspecialchars($p['corpo'] ?? '') ?>"
                                    data-agency="<?= htmlspecialchars($p['agency'] ?? '') ?>">
                                    
                                    <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' ' . $p['suffix']) ?></td>
                                    <td data-branch-code="<?= htmlspecialchars($p['branch']) ?>">
                                        <?= htmlspecialchars($branchMap[$p['branch']]['branch'] ?? '-') ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['brand'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['status'] ?? '-') ?></td>

                                    <!-- NEW -->
                                    <td><?= htmlspecialchars($p['employment_status'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['sub_status'] ?? '-') ?></td>

                                    <td><?= $p['assignment_date'] ? date('m/d/y', strtotime($p['assignment_date'])) : '-' ?></td>
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
const branchMap = <?= json_encode($branchMap, JSON_UNESCAPED_UNICODE); ?>;
</script>
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
<script src="assets/js/xlsx.full.min.js"></script>
<script src="assets/js/employee/promodizers.js"></script>
<?php include 'modals/edit_promodizer_modal.php'; ?>
<?php include 'modals/add_employee_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>