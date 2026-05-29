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
#agencyTable {
    width: 100% !important;
}

#agencyTable th {
    background-color: #2d68c4;
    color: white;
    text-align: center;
    vertical-align: middle;
    font-size: 14px;
}

#agencyTable td {
    vertical-align: middle;
    font-size: 14px;
    text-align: center;
}

#agencyTable.table-hover tbody tr:hover > td {
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

#agencyTable td:last-child,
#agencyTable th:last-child {
    white-space: nowrap;
    width: 60px !important;
}

</style>

<div class="content">
    <div class="container-fluid">

        <!-- PAGE TITLE -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="page-title mb-0">Agency Management</h4>

            <button class="btn btn-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#agencyModal">
                <i class="bi bi-plus-lg"></i>
                Add Agency
            </button>
        </div>

        <!-- CARD -->
        <div class="card shadow-sm">

            <div class="card-body">

                <div class="table-responsive">
                    <table id="agencyTable"
                        class="table table-bordered table-hover align-middle">

                        <thead>
                            <tr>
                                <th>Agency</th>
                                <th>Contact Person</th>
                                <th>Mobile #</th>
                                <th>Telephone #</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th width="18%">Actions</th>
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
<div class="modal fade" id="agencyModal" tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form id="agencyForm">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Agency
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" id="agencyId">

                    <!-- Agency Name -->
                    <div class="mb-3">
                        <label class="form-label">Agency Name</label>
                        <input type="text"
                            id="agencyName"
                            class="form-control text-uppercase"
                            required>
                    </div>

                    <!-- Contact Person -->
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text"
                            id="contactPerson"
                            class="form-control text-uppercase"
                            required>
                    </div>

                    <!-- MOBILE NUMBERS -->
                    <div class="mb-3">
                        <label class="form-label d-block">Mobile Numbers</label>

                        <div id="mobileContainer">

                            <div class="input-group mb-2">
                                <input type="text"
                                    name="contact_numbers[]"
                                    class="form-control mobile-input"
                                    placeholder="Mobile Number">
                            </div>

                        </div>

                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                id="addMobileBtn">
                            <i class="bi bi-plus-lg"></i> Add Mobile
                        </button>
                    </div>

                    <!-- TELEPHONE NUMBERS -->
                    <div class="mb-3">
                        <label class="form-label d-block">Telephone Numbers</label>

                        <div id="telephoneContainer">

                            <div class="input-group mb-2">
                                <input type="text"
                                    name="tel_numbers[]"
                                    class="form-control telephone-input"
                                    placeholder="Telephone Number">
                            </div>

                        </div>

                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                id="addTelephoneBtn">
                            <i class="bi bi-plus-lg"></i> Add Telephone
                        </button>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="text"
                            id="email"
                            class="form-control"
                            required>
                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-primary">
                        Save Agency
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
<script>
$(document).ready(function () {

    // =========================
    // ADD MOBILE
    // =========================
    $("#addMobileBtn").click(function () {

        let count = $("#mobileContainer .input-group").length;

        if (count >= 3) {
            Swal.fire({
                icon: "warning",
                title: "Limit Reached",
                text: "Maximum of 3 mobile numbers only."
            });
            return;
        }

        $("#mobileContainer").append(`
            <div class="input-group mb-2">
                <input type="text"
                       name="contact_numbers[]"
                       class="form-control mobile-input"
                       placeholder="Mobile Number">

                <button type="button"
                        class="btn btn-outline-danger remove-mobile">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        `);
    });

    // REMOVE MOBILE
    $(document).on("click", ".remove-mobile", function () {
        $(this).closest(".input-group").remove();
    });

    // =========================
    // ADD TELEPHONE
    // =========================
    $("#addTelephoneBtn").click(function () {

        let count = $("#telephoneContainer .input-group").length;

        if (count >= 3) {
            Swal.fire({
                icon: "warning",
                title: "Limit Reached",
                text: "Maximum of 3 telephone numbers only."
            });
            return;
        }

        $("#telephoneContainer").append(`
            <div class="input-group mb-2">
                <input type="text"
                       name="tel_numbers[]"
                       class="form-control telephone-input"
                       placeholder="Telephone Number">

                <button type="button"
                        class="btn btn-outline-danger remove-telephone">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        `);
    });

    // REMOVE TELEPHONE
    $(document).on("click", ".remove-telephone", function () {
        $(this).closest(".input-group").remove();
    });

});
</script>
<script src="assets/js/agencies/agencies.js"></script>

<?php include 'modals/change_password_modal.php'; ?>