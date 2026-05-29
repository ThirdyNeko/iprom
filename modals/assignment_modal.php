<?php
$isAllowed =
    (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
    (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');
?>

<style>
/* =========================
   ONLY APPLY INSIDE ASSIGNMENT MODAL
   ========================= */

/* READONLY TABLE CELLS (yellow) */
#assignmentModal .table td.readonly-field {
    background-color: #e9ecef !important;
    color: #555;
    vertical-align: middle;
}

/* EDITABLE INPUTS (yellow) */
#assignmentModal .form-control:not([readonly]):not([disabled]),
#assignmentModal .form-select:not([disabled]) {
    background-color: #fffbdf !important;
    opacity: 1;
}

/* READONLY / DISABLED INPUTS (grey) */
#assignmentModal .form-control[readonly-field],
#assignmentModal .form-control[disabled],
#assignmentModal .form-select[disabled] {
    background-color: #e9ecef !important;
    opacity: 1;
    cursor: not-allowed;
}
.centered-input {
    text-align: center;
    width: 100px;
    margin: 0 auto;
    display: block;
}

#assignmentModal th{
    text-align: left;
    width: 45%;
}

</style>
<div class="modal fade" id="assignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg"> <!-- ✅ wider like promodizer -->
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Assignment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- Optional alert placeholder -->
                <div id="assignmentAlert"></div>

                <div class="row">
                    <!-- Left: Assignment Info -->
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm mb-0 text-center align-middle">
                            <tbody>
                                <tr>
                                    <th>Branch</th>
                                    <td id="modalBranch" class="readonly-field"></td>
                                </tr>
                                <tr>
                                    <th>Brand</th>
                                    <td id="modalBrand" class="readonly-field"></td>
                                </tr>
                                <tr>
                                    <th>Plantilla Count</th>
                                    <td>
                                        <input type="number" id="modalRequired" class="form-control form-control-sm centered-input" min="0" <?= !$isAllowed ? 'disabled' : '' ?>>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td id="modalStatus" class="readonly-field"></td>
                                </tr>
                                <tr>
                                    <th>Date Last Updated</th>
                                    <td id="modalUpdated" class="readonly-field"></td>
                                </tr>
                                <tr>
                                    <th>Last Updated By</th>
                                    <td id="modalUpdatedBy" class="readonly-field"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Right: Assigned Employees -->
                    <div class="col-md-6">
                        <h6>Promodisers</h6>
                        <div id="modalAssignedList" style="max-height: 300px; overflow-y: auto;">
                            <small class="text-muted">Loading...</small>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin') || ($_SESSION['role'] === 'super_admin')): ?>
            <div class="modal-footer">                
                <button type="button" class="btn btn-primary" id="saveRequiredBtn" <?= !$isAllowed ? 'disabled' : '' ?>>Save</button>                
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script src="assets/js/assignment/assignment_modal.js"></script>

