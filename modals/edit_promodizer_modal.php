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
                <input type="hidden" id="editPromodizerId">

                <table class="table table-bordered table-striped table-sm mb-3">
                    <tbody>
                        <tr>
                            <th>First Name</th>
                            <td><input type="text" id="editFirstName" class="form-control" readonly></td>
                            <th>Last Name</th>
                            <td><input type="text" id="editLastName" class="form-control" readonly></td>
                        </tr>
                        <tr>
                            <th>Branch</th>
                            <td><input type="text" id="editBranch" class="form-control" readonly></td>
                            <th>Brand</th>
                            <td><input type="text" id="editBrand" class="form-control" readonly></td>
                        </tr>
                        <tr>
                            <th>Employment Status</th>
                            <td>
                                <select id="editEmploymentStatus" class="form-select">
                                    <option value="">-- Select Status --</option>
                                    <option value="PERMANENT">PERMANENT</option>
                                    <option value="SEASONAL">SEASONAL</option>
                                    <option value="RELIEVER">RELIEVER</option>
                                </select>
                            </td>

                            <th>Sub Status</th>
                            <td>
                                <select id="editSubStatus" class="form-select">
                                    <option value="">-- Select Sub Status --</option>
                                    <option value="STATIONARY">STATIONARY</option>
                                    <option value="MULTI BRANCH">MULTI BRANCH</option>
                                    <option value="MULTI BRAND">MULTI BRAND</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><input type="text" id="editStatus" class="form-control" readonly></td>
                            <th>Last Assigned By</th>
                            <td><input type="text" id="editLastAssignedBy" class="form-control" readonly></td>
                        </tr>
                        <tr>
                            <th>Assignment Date</th>
                            <td><input type="text" id="editAssignmentDate" class="form-control" readonly></td>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <th>Reason for Update</th>
                            <td colspan="3">
                                <select id="editReasonUpdate" class="form-select">
                                    <option value="">-- Select Reason --</option>
                                    <option value="RESIGNED">RESIGNED</option>
                                    <option value="PULL-OUT / TERMINATED">PULL-OUT / TERMINATED</option>
                                    <option value="MATERNITY LEAVE">MATERNITY LEAVE</option>
                                    <option value="AWOL">AWOL</option>
                                    <option value="TRANSFER">TRANSFER</option>
                                    <option value="RETRENCHMENT">RETRENCHMENT</option>
                                    <option value="PROMOTED (MT)">PROMOTED (MT)</option>
                                    <option value="END OF CONTRACT">END OF CONTRACT</option>
                                    <option value="BLACKLISTED">BLACKLISTED</option>
                                </select>
                            </td>
                        </tr>
                        <tr id="rowDateSeparated">
                            <th>Date Separated</th>
                            <td><input type="date" id="editDateSeparated" class="form-control"></td>
                            <th></th>
                            <td></td>
                        </tr>

                        <tr id="rowDateReturned">
                            <th>Date Returned</th>
                            <td><input type="date" id="editDateReturn" class="form-control"></td>
                            <td colspan="2"></td>
                        </tr>

                        <tr id="rowStartDate">
                            <td>Start Date</td>
                            <td><input type="date" id="editStartDate"></td>
                        </tr>

                        <tr id="rowEndDate">
                            <td>End Date</td>
                            <td><input type="date" id="editEndDate"></td>
                        </tr>
                        <tr>
                            <th>Last Updated By</th>
                            <td><input type="text" id="editLastUpdatedBy" class="form-control" readonly></td>
                            <th>Date Last Updated</th>
                            <td><input type="date" id="editDateLastUpdated" class="form-control" readonly></td>
                        </tr>
                        <tr>
                            <th>Remarks</th>
                            <td colspan="3">
                                <input type="text" id="editRemarks" class="form-control" maxlength="100" placeholder="e.g. immediate resignation, pull out product">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="modal-footer d-flex justify-content-end">
                <button type="button" class="btn btn-primary" id="saveBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/edit_promodizer.js"></script>