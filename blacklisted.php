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
        text-align: center;
        vertical-align: middle;
    }
    #Blacklistedtable th:first-child,
    #Blacklistedtable td:first-child {
        border-left: 1px solid #dee2e6;
    }
    #Blacklistedtable td:first-child {
        text-align: left;
    }
    #Blacklistedtable th {
        background-color: #2d68c4;
        color: white;
    }
    #Blacklistedtable.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }

    .filter-control {
        height: 32px !important;
        font-size: 14px;
    }

    .clear-input { position: relative; }
    .clear-input input { padding-right: 28px; }
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
    .clear-btn:hover { color: #333; }
</style>

<div class="content">
    <div class="container-fluid">

        <!-- Page header / toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Blacklisted</h4>
            <div class="d-flex gap-2">
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'assistant_admin'])): ?>
                <button type="button" class="btn btn-sm btn-primary" id="syncBlacklistBtn">
                    <i class="bi bi-arrow-repeat"></i> Sync from Employees
                </button>
                <button type="button" class="btn btn-sm btn-success" id="addBlacklistedPromodiserBtn" data-bs-toggle="modal" data-bs-target="#addBlacklistedPromodiserModal">
                    <i class="bi bi-plus-lg"></i> Add Promodiser
                </button>
                <button type="button" class="btn btn-sm btn-success" id="addBlacklistedDirectHireBtn" data-bs-toggle="modal" data-bs-target="#addBlacklistedDirectHireModal">
                    <i class="bi bi-plus-lg"></i> Add Direct Hire
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="clear-input">
                            <input type="text" id="filterName"
                                class="form-control form-control-sm filter-control"
                                placeholder="First, Middle, or Last Name">
                            <button type="button" class="clear-btn" data-target="filterName">×</button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select id="filterCategory" class="form-select form-select-sm filter-control">
                            <option value="all">All</option>
                            <option value="promodiser">Promodiser</option>
                            <option value="direct_hire">Direct Hire</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table id="Blacklistedtable" class="table table-striped table-hover align-middle text-center w-100">
                        <thead>
                            <tr>
                                <th class="d-none">ID</th>
                                <th>Full Name</th>
                                <th>Type</th>
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
<script src="assets/js/blacklisted/add_blacklisted_promodiser.js"></script>
<script src="assets/js/blacklisted/add_blacklisted_direct_hire.js"></script>
<script src="assets/js/blacklisted/view_blacklisted.js"></script>
<script>
document.querySelectorAll(".clear-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    const input = document.getElementById(btn.getAttribute("data-target"));
    input.value = "";
    input.dispatchEvent(new Event("input"));
  });
});
</script>

<?php include 'modals/add_blacklisted_promodiser_modal.php'; ?>
<?php include 'modals/add_blacklisted_direct_hire_modal.php'; ?>
<?php include 'modals/view_blacklisted_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>