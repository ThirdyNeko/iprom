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
    #logsTable th,
    #logsTable td {
        border-right: 1px solid #dee2e6;
    }
    #logsTable.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }
    #logsTable th
    {
        text-align: center;
        vertical-align: middle;
        background-color: #2d68c4;
        color : white;
    }

    #logsTable td {
        font-size: 14px;
    }

    #logsTable th:first-child,
    #logsTable td:first-child {
        border-left: 1px solid #dee2e6; /* remove extra line at start */
        text-align: center !important;
    }
    #logsTable td:nth-child(4) {
        text-align: center !important;
    }
    #logsTable td:last-child {
        text-align: center !important;
    }

    .card-body .col {
        min-width: 150px;
    }
</style>
<div class="content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col">
                <h4 class="fw-bold mb-0">Employee Logs</h4>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-2 mb-3">

                    <div class="col">
                        <label class="form-label">User</label>
                        <input type="text" id="filterUser" class="form-control" placeholder="Search user">
                    </div>

                    <div class="col">
                        <label class="form-label">Reason</label>
                        <select id="filterReason" class="form-select">
                            <option value="">All</option>
                            <option value="RESIGNED">RESIGNED</option>
                            <option value="PULL-OUT / END OF CONTRACT">PULL-OUT / END OF CONTRACT</option>
                            <option value="MATERNITY LEAVE">MATERNITY LEAVE</option>
                            <option value="EMERGENCY LEAVE">EMERGENCY LEAVE</option>
                            <option value="TRANSFER BRANCH">TRANSFER BRANCH</option>
                            <option value="BLOCKLISTED / AWOL / TERMINATED">BLOCKLISTED / AWOL / TERMINATED</option>
                            <option value="CHANGE EMPLOYMENT STATUS">CHANGE EMPLOYMENT STATUS</option>
                            <option value="CHANGE SUB STATUS">CHANGE SUB STATUS</option>
                            <option value="REMOVED CURRENT BRANCH/BRAND">REMOVED CURRENT BRANCH/BRAND</option>
                            <option value="ADD BRANCH/BRAND">ADD BRANCH/BRAND</option>
                            <option value="AUTO REACTIVATED">AUTO REACTIVATED</option>
                            <option value="AUTO DEACTIVATED">AUTO DEACTIVATED</option>
                            <option value="ADDED EMPLOYEE">ADDED EMPLOYEE</option>
                        </select>
                    </div>

                    <div class="col">
                        <label class="form-label">Remarks</label>
                        <input type="text" id="filterRemarks" class="form-control" placeholder="Search remarks">
                    </div>

                    <div class="col">
                        <label class="form-label">From</label>
                        <input type="date" id="filterFrom" class="form-control">
                    </div>

                    <div class="col">
                        <label class="form-label">To</label>
                        <input type="date" id="filterTo" class="form-control">
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
<script src="assets/js/log/logs.js"></script>