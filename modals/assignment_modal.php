<style>
/* FORCE override Bootstrap table cells */
.table > tbody > tr > td.readonly-field {
    background-color: #fff9c4 !important;
}

/* Optional: improve appearance */
.table > tbody > tr > td.readonly-field {
    color: #555;
    vertical-align: middle;
}

/* For inputs */
.form-control[readonly],
.form-select[readonly],
select:disabled {
    background-color: #fff9c4 !important;
    opacity: 1;
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

