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
                                <th width="10%">ID</th>
                                <th>Agency</th>
                                <th width="18%">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php

                            $stmt = $pdo->query("
                                SELECT id, agencies
                                FROM agencies
                                ORDER BY agencies ASC
                            ");

                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) :
                            ?>

                            <tr>

                                <td class="text-center">
                                    <?= $row['id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['agencies']) ?>
                                </td>

                                <td>

                                    <div class="action-btns">

                                        <button
                                            class="btn btn-warning btn-sm editAgencyBtn"
                                            data-id="<?= $row['id'] ?>"
                                            data-name="<?= htmlspecialchars($row['agencies']) ?>">
                                            Edit
                                        </button>

                                        <button
                                            class="btn btn-danger btn-sm deleteAgencyBtn"
                                            data-id="<?= $row['id'] ?>">
                                            Remove
                                        </button>

                                    </div>

                                </td>

                            </tr>

                            <?php endwhile; ?>
                        </tbody>

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

                    <div class="mb-3">

                        <label class="form-label">
                            Agency Name
                        </label>

                        <input type="text"
                               id="agencyName"
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
<script src="swweetalert/dist/sweetalert2.all.min.js"></script>

<script src="assets/js/agencies/agencies.js"></script>

<?php include 'modals/change_password_modal.php'; ?>