<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

$isAllowed =
    (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');

if (!$isAllowed) {
    header('Location: dashboard.php');
    exit;
}   

?>


<style>
/* =========================
   PAGE
========================= */
.page-title {
    font-size: 24px;
    font-weight: 700;
    color: #2d3436;
}

/* =========================
   CARD
========================= */
.card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    max-width: 45%;   /* ← constrain the whole card */
}

.card-header-custom {
    background: #2d68c4;
    color: white;
    padding: 14px 18px;
    font-size: 16px;
    font-weight: 600;
}

/* remove the old .card-body { max-width: 600px } rule entirely */

/* =========================
   TABLE
========================= */
.table-responsive {
    width: 100%;   /* ← no max-width needed; card already caps it */
}

#brandTable {
    width: 100% !important;
}

#brandTable th {
    background-color: #2d68c4;
    color: white;
    text-align: center;
    vertical-align: middle;
    font-size: 14px;
}

#brandTable td {
    vertical-align: middle;
    font-size: 14px;
    text-align: center;
}

#brandTable td:nth-child(1),
#brandTable td:nth-child(2){
    text-align: left;
}

#brandTable.table-hover tbody tr:hover > td {
    background-color: #eef4ff !important;
}

/* =========================
   BUTTONS
========================= */
.btn-sm {
    font-size: 13px;
    padding: 4px 10px;
}

.action-btns {
    display: flex;
    justify-content: center;
    gap: 6px;
}

/* =========================
   MODAL
========================= */
.modal-header {
    background: #2d68c4;
    color: white;
}

.modal-title {
    font-size: 16px;
    font-weight: 600;
}

.text-uppercase {
  text-transform: uppercase;
}

#brandTable td:last-child,
#brandTable th:last-child {
    white-space: nowrap;
    width: 60px !important;
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

        <!-- PAGE TITLE -->
        <div class="d-flex justify-content-between align-items-center mb-3"
            style="max-width: 600px;">
            <h4 class="page-title mb-0">Brand Management</h4>

        </div>

        <!-- CARD -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-2 justify-content-between align-items-end">

                    <!-- NAME SEARCH -->
                    <div class="col-md-6">
                        <label class="form-label">Search</label>
                        <div class="clear-input">
                            <input type="text" id="filterName"
                                class="form-control form-control-sm filter-control"
                                placeholder="Brand">
                            <button type="button" class="clear-btn" data-target="filterName">×</button>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#brandModal">
                            <i class="bi bi-plus-lg"></i>
                            Add Brand
                        </button>
                    </div>

                </div>
            </div>

            <div class="card-body">

                <div class="table-responsive">
                    <table id="brandTable"
                        class="table table-bordered table-hover align-middle">

                        <thead>
                            <tr>
                                <th>Brand</th>
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

<!-- =========================
     ADD / EDIT MODAL
========================= -->
<div class="modal fade" id="brandModal" tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form id="brandForm">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Brand
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" id="brandId">

                    <!-- Brand Name -->
                    <div class="mb-3">
                        <label class="form-label">Brand Name</label>
                        <input type="text"
                            id="brandName"
                            class="form-control text-uppercase"
                            required>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" id="saveBrandBtn" class="btn btn-primary">
                        Save Brand
                    </button>

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    
                </div>

            </form>

        </div>
    </div>
</div>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/brands/brands.js"></script>
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