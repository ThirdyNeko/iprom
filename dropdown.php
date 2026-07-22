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
    height: 100%;
}

.card-header-custom {
    background: #2d68c4;
    color: white;
    padding: 14px 18px;
    font-size: 16px;
    font-weight: 600;
}

/* =========================
   TABLE
========================= */
.table-responsive {
    width: 100%;
}

#suffixTable,
#categoriesTable {
    width: 100% !important;
}

#suffixTable th,
#categoriesTable th {
    background-color: #2d68c4;
    color: white;
    text-align: center;
    vertical-align: middle;
}

#suffixTable td,
#categoriesTable td {
    vertical-align: middle;
    text-align: center;
}

#suffixTable td:nth-child(1),
#categoriesTable td:nth-child(1) {
    text-align: left;
}

.table-hover tbody tr:hover > td {
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

#suffixTable td:last-child,
#suffixTable th:last-child,
#categoriesTable td:last-child,
#categoriesTable th:last-child {
    white-space: nowrap;
    width: 80px !important;
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="page-title mb-0">Suffix &amp; Categories Management</h4>
        </div>

        <div class="row g-3">

            <!-- =========================
                 SUFFIX CARD
            ========================= -->
            <div class="col-md-6">
                <div class="card shadow-sm">

                    <div class="card-body">
                        <div class="row g-2 justify-content-between align-items-end">

                            <div class="col-md-7">
                                <label class="form-label">Search</label>
                                <div class="clear-input">
                                    <input type="text" id="filterSuffix"
                                        class="form-control form-control-sm filter-control"
                                        placeholder="Suffix">
                                    <button type="button" class="clear-btn" data-target="filterSuffix">×</button>
                                </div>
                            </div>

                            <div class="col-md-5 text-end">
                                <button class="btn btn-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#suffixModal">
                                    <i class="bi bi-plus-lg"></i>
                                    Add Suffix
                                </button>
                            </div>

                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="suffixTable"
                                class="table table-bordered table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Suffix</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

            <!-- =========================
                 CATEGORIES CARD
            ========================= -->
            <div class="col-md-6">
                <div class="card shadow-sm">

                    <div class="card-body">
                        <div class="row g-2 justify-content-between align-items-end">

                            <div class="col-md-7">
                                <label class="form-label">Search</label>
                                <div class="clear-input">
                                    <input type="text" id="filterCategory"
                                        class="form-control form-control-sm filter-control"
                                        placeholder="Category">
                                    <button type="button" class="clear-btn" data-target="filterCategory">×</button>
                                </div>
                            </div>

                            <div class="col-md-5 text-end">
                                <button class="btn btn-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#categoryModal">
                                    <i class="bi bi-plus-lg"></i>
                                    Add Category
                                </button>
                            </div>

                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="categoriesTable"
                                class="table table-bordered table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Category</th>
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

    </div>
</div>

<!-- =========================
     ADD / EDIT SUFFIX MODAL
========================= -->
<div class="modal fade" id="suffixModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="suffixForm">

                <div class="modal-header">
                    <h5 class="modal-title">Suffix</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="suffixId">

                    <div class="mb-3">
                        <label class="form-label">Suffix</label>
                        <input type="text" id="suffixName" class="form-control text-uppercase" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" id="saveSuffixBtn" class="btn btn-primary">Save Suffix</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- =========================
     ADD / EDIT CATEGORY MODAL
========================= -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="categoryForm">

                <div class="modal-header">
                    <h5 class="modal-title">Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="categoryId">

                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" id="categoryName" class="form-control text-uppercase" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" id="saveCategoryBtn" class="btn btn-primary">Save Category</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/dropdown/dropdown.js"></script>
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