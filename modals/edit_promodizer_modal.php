<!-- Edit Promodizer Modal -->
<div class="modal fade" id="editPromodizerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Promodizer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="editAlert"></div>

                <!-- 🔴 Terminated Notice -->
                <div id="terminatedNotice" class="alert alert-danger d-none">
                    This employee is terminated and can no longer be modified.
                </div>

                <!-- Hidden ID -->
                <input type="hidden" id="editPromodizerId">

                <!-- Employee Info Table -->
                <table class="table table-bordered table-striped table-sm mb-3">
                    <tbody>
                        <tr>
                            <th>First Name</th>
                            <td><input type="text" id="editFirstName" class="form-control"></td>
                            <th>Last Name</th>
                            <td><input type="text" id="editLastName" class="form-control"></td>
                        </tr>

                        <script>
                        document.getElementById('editFirstName').addEventListener('input', function() {
                            this.value = this.value.toUpperCase();
                        });
                        document.getElementById('editLastName').addEventListener('input', function() {
                            this.value = this.value.toUpperCase();
                        });
                        </script>
                        <tr>
                            <th>Branch</th>
                            <td>
                                <select id="editBranch" class="form-select"></select>
                            </td>

                            <th>Brand</th>
                            <td>
                                <select id="editBrand" class="form-select"></select>
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td id="editStatus"></td>
                            <th>Last Assigned By</th>
                            <td id="editLastAssignedBy"></td>
                        </tr>
                        <tr>
                            <th>Assignment Date</th>
                            <td id="editAssignmentDate"></td>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <th>Date Hired</th>
                            <td id="editDateHired"></td>
                            <th>Date of Return</th>
                            <td id="editDateReturn"></td>
                        </tr>
                        <tr>
                            <th>Date Separated</th>
                            <td id="editDateSeparated"></td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-danger" id="terminateBtn">Terminate</button>
                    <button type="button" class="btn btn-warning" id="unassignBtn">Unassign</button>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" id="saveBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/edit_promodizer.js"></script>