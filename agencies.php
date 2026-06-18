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
                <div class="row g-2 align-items-end">

                    <!-- NAME SEARCH -->
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="clear-input">
                            <input type="text" id="filterName"
                                class="form-control form-control-sm filter-control"
                                placeholder="Agency, Contact Person, Email">
                            <button type="button" class="clear-btn" data-target="filterName">×</button>
                        </div>
                    </div>

                </div>
            </div>

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
                                <th width="18%">Action</th>
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
                        <label class="form-label d-block">Mobile Number</label>

                        <div id="mobileContainer">

                            <div class="input-group mb-2">
                                <input type="text"
                                    name="contact_numbers[]"
                                    class="form-control mobile-input"
                                    placeholder="09XX XXX XXXX"
                                    maxlength="13">
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
                        <label class="form-label d-block">Telephone Number</label>

                        <div id="telephoneContainer">

                            <div class="input-group mb-2">
                                <input type="text"
                                    name="tel_numbers[]"
                                    class="form-control telephone-input"
                                    placeholder="(XXX) XXX-XX-XX"
                                    maxlength="15">
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
                    <button type="submit" id="saveAgencyBtn" class="btn btn-primary">
                        Save Agency
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
<script>
document.addEventListener("input", function (e) {
    if (e.target.classList.contains("mobile-input")) {
        let value = e.target.value.replace(/\D/g, '');

        // limit to 11 digits (09XX XXX XXXX)
        value = value.substring(0, 11);

        // format: XXXX XXX XXXX
        if (value.length > 7) {
            value = value.substring(0, 4) + ' ' + value.substring(4, 7) + ' ' + value.substring(7);
        } else if (value.length > 4) {
            value = value.substring(0, 4) + ' ' + value.substring(4);
        }

        e.target.value = value;
    }
});

document.addEventListener("input", function (e) {
    if (e.target.classList.contains("telephone-input")) {
        let value = e.target.value.replace(/\D/g, '');

        // limit to 10 digits (XXX XXX XX XX)
        value = value.substring(0, 10);

        // format: (XXX) XXX-XX-XX — branch on actual digit count to avoid trailing separators
        if (value.length > 8) {
            value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 8) + '-' + value.substring(8);
        } else if (value.length > 6) {
            value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6);
        } else if (value.length > 3) {
            value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
        } else if (value.length > 0) {
            value = '(' + value;
        }

        e.target.value = value;
    }
});
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
                        placeholder="09XX XXX XXXX"
                        maxlength="13">

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
                        placeholder="(XXX) XXX-XX-XX"
                        maxlength="15">

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

    $("#agencyForm").on("submit", function (e) {
        let valid = true;

        $(".mobile-input").each(function () {
            let val = $(this).val().trim();
            if (val && !/^\d{4} \d{3} \d{4}$/.test(val)) {
                valid = false;
                $(this).addClass("is-invalid");
            } else {
                $(this).removeClass("is-invalid");
            }
        });

        $(".telephone-input").each(function () {
            let val = $(this).val().trim();
            if (val && !/^\(\d{3}\) \d{3}-\d{2}-\d{2}$/.test(val)) {
                valid = false;
                $(this).addClass("is-invalid");
            } else {
                $(this).removeClass("is-invalid");
            }
        });

        if (!valid) {
            e.preventDefault();
            Swal.fire({
                icon: "error",
                title: "Invalid Format",
                text: "Please check your mobile/telephone number formats."
            });
        }
    });
});
</script>
<script src="assets/js/agencies/agencies.js"></script>
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