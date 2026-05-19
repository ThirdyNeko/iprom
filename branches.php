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
    #Branchtable th,
    #Branchtable td {
        border-right: 1px solid #dee2e6;
    }
    #Branchtable.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }
    #Branchtable th
    {
        text-align: center;
        vertical-align: middle;
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

    #Branchtable td {
        font-size: 14px;
    }

    #Branchtable th:first-child,
    #Branchtable td:first-child {
        border-left: 1px solid #dee2e6; /* remove extra line at start */
        text-align: center !important;
    }
    #Branchtable td:nth-child(4) {
        text-align: center !important;
    }
    #Branchtable td:last-child {
        text-align: center !important;
    }
</style>

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Branches</h4>

            <button id="syncBranchesBtn" class="btn btn-success">
                ⟳ Sync Branches
            </button>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="Branchtable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th>Branch</th>
                                <th>Company</th>
                                <th>Region</th>
                                <th>Area</th>
                                <th>Status</th>
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
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/branches/branches.js"></script>

<?php include 'modals/change_password_modal.php'; ?>