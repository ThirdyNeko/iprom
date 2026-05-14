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
    .card-body .col {
        min-width: 220px;
    }

    /* prevent select2 overflow issues */
    .select2-container {
        width: 100% !important;
    }
</style>

<div class="content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col">
                <h4 class="fw-bold mb-0">Merge / Unmerge Employees</h4>
            </div>
        </div>

        <!-- ================= MERGE ================= -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                Merge Employees
            </div>

            <div class="card-body">
                <form id="mergeForm">

                    <div class="row g-2 mb-3">

                        <div class="col-12 col-md-6">
                            <label class="form-label">Primary Employee (Keep)</label>
                            <select name="primary_employee"
                                    class="form-select employee-search"
                                    required></select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Secondary Employee (Merge)</label>
                            <select name="secondary_employee"
                                    class="form-select employee-search"
                                    required></select>
                        </div>

                    </div>

                    <button type="submit" class="btn btn-primary">
                        Merge Employees
                    </button>

                </form>
            </div>
        </div>

        <!-- ================= UNMERGE ================= -->
        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white">
                Unmerge Employees
            </div>

            <div class="card-body">
                <form id="unmergeForm">

                    <div class="row g-2 mb-3">

                        <div class="col-12 col-md-6">
                            <label class="form-label">Merged Employee (To Restore)</label>
                            <select name="employee_id"
                                    class="form-select merged-employee-search"
                                    required></select>
                        </div>

                    </div>

                    <button type="submit" class="btn btn-danger">
                        Unmerge Employee
                    </button>

                </form>
            </div>
        </div>

    </div>
</div>

<!-- ================= SCRIPTS ================= -->
<link href="assets/css/select2.min.css" rel="stylesheet" />

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/select2.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/merge/merge_employees.js"></script>

<?php include 'modals/change_password_modal.php'; ?>