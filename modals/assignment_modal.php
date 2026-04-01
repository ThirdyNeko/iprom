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
                                    <td id="modalBranch"></td>
                                </tr>
                                <tr>
                                    <th>Brand</th>
                                    <td id="modalBrand"></td>
                                </tr>
                                <tr>
                                    <th>Required</th>
                                    <td>
                                        <input type="number" id="modalRequired" class="form-control form-control-sm" min="0">
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td id="modalStatus"></td>
                                </tr>
                                <tr>
                                    <th>Updated At</th>
                                    <td id="modalUpdated"></td>
                                </tr>
                                <tr>
                                    <th>Updated By</th>
                                    <td id="modalUpdatedBy"></td>
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

