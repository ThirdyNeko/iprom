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
table td {
    text-align: left !important;
}

/* =========================
   TABLE STYLING
========================= */

#logsTable th,
#logsTable td {
    border-right: 1px solid #dee2e6;
}

#logsTable.table-hover tbody tr:hover > td {
    background-color: #e6f0ff !important;
}

#logsTable th {
    text-align: center;
    vertical-align: middle;
    background-color: #2d68c4;
    color: white;
}

#logsTable td {
    font-size: 14px;
    vertical-align: middle;
}

#logsTable th:first-child,
#logsTable td:first-child {
    border-left: 1px solid #dee2e6;
    text-align: center !important;
}

#logsTable td:nth-child(4),
#logsTable td:last-child {
    text-align: center !important;
}

/* =========================
   FILTER BAR LAYOUT
========================= */

.filters-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
    margin-bottom: 15px;
}

.filter-group {
    flex: 1;
    min-width: 180px;
}

/* Label */
.filter-label {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
    color: #555;
}

/* Inputs */
.filter-control {
    height: 34px !important;
    font-size: 14px;
    border-radius: 6px;
}

/* =========================
   CLEAR INPUT BUTTON
========================= */

.clear-input {
    position: relative;
}

.clear-input input {
    padding-right: 30px;
    border-radius: 6px;
}

.clear-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    font-size: 16px;
    color: #888;
    cursor: pointer;
    line-height: 1;
}

.clear-btn:hover {
    color: #000;
}

/* =========================
   CHECKBOX (Remarks filter)
========================= */

.form-check {
    margin-top: 4px;
}

.form-check-label {
    font-size: 13px;
    color: #555;
}

#filterRemarks:disabled {
    background-color: #f1f3f5;
    cursor: not-allowed;
    opacity: 0.7;
}

/* =========================
   CARD CLEANUP
========================= */

.card-body {
    padding: 1.25rem;
}

/* Remove old Bootstrap column conflicts */
.card-body .row.g-2 .col,
.card-body .col {
    min-width: unset;
}

/* =========================
   RESPONSIVE FIX
========================= */

@media (max-width: 768px) {
    .filters-row {
        flex-direction: column;
    }

    .filter-group {
        width: 100%;
    }
}
</style>
<div class="content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col">
                <h4 class="fw-bold mb-0">Employee Logs</h4>
            </div>
            <div class="col-md-6 text-md-end mt-2 mt-md-0">
                <div class="d-flex justify-content-md-end gap-2 flex-wrap">
                    <button id="exportExcel" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="filters-row mb-3">

                    <!-- USER -->
                    <div class="filter-group">
                        <div class="filter-label">User</div>
                        <div class="clear-input">
                            <input type="text" id="filterUser" class="form-control form-control-sm filter-control" placeholder="Search user">
                            <button type="button" class="clear-btn" data-target="filterUser">×</button>
                        </div>
                    </div>

                    <!-- REASON -->
                    <div class="filter-group">
                        <div class="filter-label">Reason</div>
                        <select id="filterReason" class="form-select form-select-sm filter-control">
                            <option value="">All</option>
                            <option value="RESIGNED">RESIGNED</option>
                            <option value="PULL-OUT / END OF CONTRACT">PULL-OUT / END OF CONTRACT</option>
                            <option value="MATERNITY LEAVE">MATERNITY LEAVE</option>
                            <option value="EMERGENCY LEAVE">EMERGENCY LEAVE</option>
                            <option value="TRANSFER BRANCH">TRANSFER BRANCH</option>
                            <option value="BLACKLISTED / AWOL / TERMINATED">BLACKLISTED / AWOL / TERMINATED</option>
                            <option value="CHANGE EMPLOYMENT STATUS">CHANGE EMPLOYMENT STATUS</option>
                            <option value="CHANGE SUB STATUS">CHANGE SUB STATUS</option>
                            <option value="REMOVED CURRENT BRANCH/BRAND">REMOVED CURRENT BRANCH/BRAND</option>
                            <option value="ADD BRANCH/BRAND">ADD BRANCH/BRAND</option>
                            <option value="AUTO REACTIVATED">AUTO REACTIVATED</option>
                            <option value="AUTO UPDATED">AUTO UPDATED</option>
                            <option value="AUTO ACTIVATED">AUTO ACTIVATED</option>
                            <option value="AUTO DEACTIVATED">AUTO DEACTIVATED</option>
                            <option value="ASSIGNED">ASSIGNED/REASSIGNED</option>
                        </select>
                    </div>

                    <!-- REMARKS -->
                    <div class="filter-group">

                        <div class="d-flex align-items-center mb-1 gap-2">

                            <div class="filter-label mb-0 me-1">Remarks</div>

                            <div class="form-check m-0">
                                <input class="form-check-input" type="checkbox" id="filterRemarksEmpty">
                                <label class="form-check-label small" for="filterRemarksEmpty">
                                    Empty
                                </label>
                            </div>

                        </div>

                        <div class="clear-input">
                            <input type="text"
                                id="filterRemarks"
                                class="form-control form-control-sm filter-control"
                                placeholder="Search remarks">

                            <button type="button" class="clear-btn" data-target="filterRemarks">×</button>
                        </div>

                    </div>

                    <!-- FROM -->
                    <div class="filter-group">
                        <div class="filter-label">From</div>
                        <input type="date" id="filterFrom" class="form-control form-control-sm filter-control">
                    </div>

                    <!-- TO -->
                    <div class="filter-group">
                        <div class="filter-label">To</div>
                        <input type="date" id="filterTo" class="form-control form-control-sm filter-control">
                    </div>

                </div>

                <div class="table-responsive">
                    <table id="logsTable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th>User</th>                                
                                <th>Reason</th>
                                <th>Remarks</th>   
                                <th>Employee</th>                             
                                <th>Update Date</th>
                            </tr>
                        </thead>

                        <tbody></tbody>

                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/xlsx.full.min.js"></script>
<script src="assets/js/log/logs.js"></script>

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
<?php include 'modals/change_password_modal.php'; ?>