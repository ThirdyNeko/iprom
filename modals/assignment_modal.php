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
</style>
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
                        <table class="table table-bordered table-sm mb-0">
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
                                    <th>Required</th>
                                    <td>
                                        <input type="number" id="modalRequired" class="form-control form-control-sm" min="0">
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td id="modalStatus" class="readonly-field"></td>
                                </tr>
                                <tr>
                                    <th>Updated At</th>
                                    <td id="modalUpdated" class="readonly-field"></td>
                                </tr>
                                <tr>
                                    <th>Updated By</th>
                                    <td id="modalUpdatedBy" class="readonly-field"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Right: Assigned Employees -->
                    <div class="col-md-6">
                        <h6>Promodizers</h6>
                        <div id="modalAssignedList" style="max-height: 300px; overflow-y: auto;">
                            <small class="text-muted">Loading...</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="saveRequiredBtn">Save</button>
            </div>

        </div>
    </div>
</div>

<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script src="assets/js/assignment_modal.js"></script>

