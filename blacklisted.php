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
    #Blacklistedtable th,
    #Blacklistedtable td {
        border-right: 1px solid #dee2e6;
    }
    #Blacklistedtable.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }
    #Blacklistedtable th
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

    #Blacklistedtable td {
        text-align: center;
    }

    #Blacklistedtable th:first-child,
    #Blacklistedtable td:first-child {
        border-left: 1px solid #dee2e6;
    }
    #Blacklistedtable td:first-child {
        text-align: left;
    }

    .clear-input {
        position: relative;
    }

    .clear-input input {
        padding-right: 28px;
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

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Blacklisted</h4>
            <button type="button" class="btn btn-sm btn-success" id="addBlacklistedBtn" data-bs-toggle="modal" data-bs-target="#addBlacklistedModal">
                <i class="bi bi-plus-lg"></i> Add Blacklisted
            </button>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-2 align-items-end">

                    <!-- NAME SEARCH -->
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="clear-input">
                            <input type="text" id="filterName"
                                class="form-control form-control-sm filter-control"
                                placeholder="First, Middle, or Last Name">
                            <button type="button" class="clear-btn" data-target="filterName">×</button>
                        </div>
                    </div>

                    <div class="col-md-auto">
                        <label class="form-label d-block">&nbsp;</label>
                        <button type="button" id="syncBlacklistBtn" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-repeat"></i> Sync from Employees
                        </button>
                    </div>

                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="Blacklistedtable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th>Full Name</th>
                                <th>Branch</th>
                                <th>Brand</th>
                                <th>Employment Status</th>
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
<script src="assets/js/blacklisted/blacklisted.js"></script>
<script src="assets/js/blacklisted/add_blacklisted.js"></script>
<script>
document.querySelectorAll(".clear-btn").forEach(btn => {
  btn.addEventListener("click", () => {

    const targetId = btn.getAttribute("data-target");
    const input = document.getElementById(targetId);

    input.value = "";

    input.dispatchEvent(new Event("input"));
  });
});
</script>

<?php include 'modals/change_password_modal.php'; ?>
<?php include 'modals/add_blacklisted_modal.php'; ?>