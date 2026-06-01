<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

$brands = $pdo->query("SELECT DISTINCT brand_name FROM assignment ORDER BY brand_name")
              ->fetchAll(PDO::FETCH_COLUMN);

$branches = $pdo->query("
    SELECT DISTINCT 
        a.branch_name AS branch_code,
        b.branch AS branch
    FROM assignment a
    LEFT JOIN IPROM.dbo.branches b
        ON a.branch_name = b.branch_code
    ORDER BY b.branch
")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .report-type-card {
        cursor: pointer;
        border: 2px solid #dee2e6;
        border-radius: 10px;
        transition: border-color .15s, background .15s, transform .12s;
        user-select: none;
    }
    .report-type-card:hover {
        border-color: #2d68c4;
        background-color: #f0f5ff;
        transform: translateY(-2px);
    }
    .report-type-card .report-icon {
        font-size: 2rem;
        line-height: 1;
    }
    .report-type-card.active .card-title {
        color: #2d68c4;
    }
</style>

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Reports</h4>
        </div>

        <p class="text-muted small mb-3">Select a report type to continue.</p>

        <div class="row g-3 mb-4" id="reportTypeGrid">

            <div class="col-12 col-sm-6 col-lg-4">
                <div class="report-type-card card shadow-sm h-100 p-3"
                     data-type="complete_plantillas"
                     data-bs-toggle="modal"
                     data-bs-target="#modalCompletePlantillas"
                     onclick="selectReportType(this)">
                    <div class="card-body d-flex align-items-start gap-3">
                        <div class="report-icon">📋</div>
                        <div>
                            <h6 class="card-title fw-bold mb-1">Complete Plantillas</h6>
                            <p class="card-text text-muted small mb-0">
                                Generate a report on all currently complete plantilla records.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-4">
                <div class="report-type-card card shadow-sm h-100 p-3"
                     data-type="vacant_plantillas"
                     data-bs-toggle="modal"
                     data-bs-target="#modalVacantPlantillas"
                     onclick="selectReportType(this)">
                    <div class="card-body d-flex align-items-start gap-3">
                        <div class="report-icon">📭</div>
                        <div>
                            <h6 class="card-title fw-bold mb-1">Vacant & Incomplete Plantillas</h6>
                            <p class="card-text text-muted small mb-0">
                                Generate a report on vacant and incomplete plantilla records.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-4">
                <div class="report-type-card card shadow-sm h-100 p-3"
                     data-type="employee_report"
                     data-bs-toggle="modal"
                     data-bs-target="#modalEmployeeReport"
                     onclick="selectReportType(this)">
                    <div class="card-body d-flex align-items-start gap-3">
                        <div class="report-icon">👤</div>
                        <div>
                            <h6 class="card-title fw-bold mb-1">Employee Report</h6>
                            <p class="card-text text-muted small mb-0">
                                Generate a report on employee information.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div id="reportFiltersArea"></div>
        <div id="reportTableArea"></div>

    </div>
</div>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/reports/reports.js"></script>

<?php
include 'modals/reports/modal_complete_plantillas.php';
include 'modals/reports/modal_vacant_plantillas.php';
include 'modals/reports/modal_employee_report.php';
include 'modals/change_password_modal.php';
?>