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
    #LOAtable th,
    #LOAtable td {
        border-right: 1px solid #dee2e6;
    }
    #LOAtable.table-hover tbody tr:hover > td {
        background-color: #e6f0ff !important;
    }
    #LOAtable th
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

    #LOAtable td {
        font-size: 14px;
    }

    #LOAtable th:first-child,
    #LOAtable td:first-child {
        border-left: 1px solid #dee2e6; /* remove extra line at start */
        text-align: center !important;
    }
    #LOAtable td:nth-child(4) {
        text-align: center !important;
    }
    #LOAtable td:last-child {
        text-align: center !important;
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

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Branch List</h4>
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
                                placeholder="Promodiser, Agency, Employment Status, Sub Status">
                            <button type="button" class="clear-btn" data-target="filterName">×</button>
                        </div>
                    </div>

                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="LOAtable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-primary">
                            <tr>
                                <th>Promodiser</th>
                                <th>Agency</th>
                                <th>Employment Status</th>
                                <th>Sub Status</th>
                                <th>Effectivity Date</th>
                                <th>Actions</th>
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
<script src="assets/js/verification/verification.js"></script>
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