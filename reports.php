<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();
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
    .report-type-card.active {
        border-color: #2d68c4;
        background-color: #e6f0ff;
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

        </div>

        <!-- Filters and table will be injected here -->
        <div id="reportFiltersArea"></div>
        <div id="reportTableArea"></div>

    </div>
</div>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
let activeReportType = null;

function selectReportType(card) {
    document.querySelectorAll('.report-type-card').forEach(c => c.classList.remove('active'));
    card.classList.add('active');
    activeReportType = card.dataset.type;

    document.getElementById('reportFiltersArea').innerHTML = '';
    document.getElementById('reportTableArea').innerHTML = '';

    // next step: load filters based on activeReportType
    console.log('Selected:', activeReportType);
}
</script>

<?php include 'modals/change_password_modal.php'; ?>