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
    #Brandtable th,
    #Brandtable td {
        border-right: 1px solid #dee2e6;
    }
    #Brandtable.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }
    #Brandtable th
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

    #Brandtable td {
        font-size: 14px;
    }

    #Brandtable th:first-child,
    #Brandtable td:first-child {
        border-left: 1px solid #dee2e6; /* remove extra line at start */
        text-align: center !important;
    }
    #Brandtable td:nth-child(4) {
        text-align: center !important;
    }
    #Brandtable td:last-child {
        text-align: center !important;
    }
</style>

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Agencies</h4>

            <button id="syncBrandsBtn" class="btn btn-success">
                ⟳ Sync Brands
            </button>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="Brandtable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th>Brand</th>
                                <th>Agency</th>
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
<script src="assets/js/agencies/agencies.js"></script>

<?php include 'modals/change_password_modal.php'; ?>