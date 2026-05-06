<style>
/* =========================
   ONLY APPLY INSIDE MODAL
   ========================= */
#editPromodizerModal .form-control:not([readonly]):not([disabled]),
#editPromodizerModal .form-select:not([disabled]) {
    background-color: #fffbdf !important; /* editable = yellow */
    opacity: 1;
}

/* readonly / disabled */
#editPromodizerModal .form-control[readonly],
#editPromodizerModal .form-control[disabled],
#editPromodizerModal .form-select[disabled] {
    background-color: #e9ecef !important; /* grey */
    opacity: 1;
    cursor: not-allowed;
}

/* =========================
   HISTORY PANEL
   ========================= */
#editPromodizerModal .history-box {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 10px;
    background: #fafafa;
}

/* individual item */
.history-item {
    padding: 6px 8px;
    border-bottom: 1px solid #eee;
}

.history-item:last-child {
    border-bottom: none;
}

/* timestamp */
.history-date {
    font-size: 12px;
    color: #888;
}

/* reason highlight */
.history-reason {
    font-weight: 600;
    color: #333;
}
</style>
<div class="modal fade" id="editPromodizerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Master Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="editAlert"></div>
                <input type="hidden" id="editPromodizerId">
                <input type="hidden" id="editEmployeeId">

                <table class="table table-bordered table-striped table-sm mb-3">
                    <tbody>
                        <tr>
                            <th>Reason for Update</th>
                            <td colspan="3">
                                <select id="editReasonUpdate" class="form-select" required>
                                    <option value="" disabled selected>-- Select Reason --</option>
                                    <option value="RESIGNED">RESIGNED</option>
                                    <option value="PULL-OUT / END OF CONTRACT">PULL-OUT / END OF CONTRACT</option>
                                    <option value="MATERNITY LEAVE">MATERNITY LEAVE</option>
                                    <option value="EMERGENCY LEAVE">EMERGENCY LEAVE</option>
                                    <option value="TRANSFER BRANCH">TRANSFER BRANCH</option>
                                    <option value="BLOCKLISTED / AWOL / TERMINATED">BLOCKLISTED / AWOL / TERMINATED</option>
                                    <option value="CHANGE EMPLOYMENT STATUS">CHANGE EMPLOYMENT STATUS</option>
                                    <option value="CHANGE SUB STATUS">CHANGE SUB STATUS</option>                                                                        
                                    <!-- <option value="REASSIGN">REASSIGN</option> -->
                                    <option value="REMOVE BRANCH/BRAND">REMOVE CURRENT BRANCH/BRAND</option>
                                    <option value="ADD BRANCH/BRAND">ADD BRANCH/BRAND</option>                                                                 
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th>Branch</th>
                            <td>
                                <select id="editBranch" class="form-control"></select>
                            </td>

                            <th>Brand</th>
                            <td>
                                <select id="editBrand" class="form-control"></select>
                            </td>
                        </tr>

                        <tr>
                            <th>First Name</th>
                            <td><input type="text" id="editFirstName" class="form-control" readonly></td>
                            <th>Date Hired</th>
                            <td><input type="date" id="editDateHired" class="form-control" readonly></td>
                        </tr>
                       
                        <tr>
                            <th>Middle Name</th>
                            <td><input type="text" id="editMiddleName" class="form-control" readonly></td>
                            <th>Employment Status</th>
                            <td>
                                <select id="editEmploymentStatus" class="form-select">
                                    <option value="PERMANENT">PERMANENT</option>
                                    <option value="RELIEVER">RELIEVER</option>
                                    <option value="SEASONAL">SEASONAL</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th>Last Name</th>
                            <td><input type="text" id="editLastName" class="form-control" readonly></td>
                            <th>Sub-Status</th>
                            <td>
                                <select id="editSubStatus" class="form-select">
                                    <option value="MULTI BRANCH">MULTI BRANCH</option>
                                    <option value="MULTI BRAND">MULTI BRAND</option>
                                    <option value="STATIONARY">STATIONARY</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th>Suffix</th>
                            <td><input type="text" id="editSuffix" class="form-control" readonly></td>
                            <th>Assignment Date</th>
                            <td><input type="text" id="editAssignmentDate" class="form-control" readonly></td>
                        </tr>

                        <tr>
                            <th>Gender</th>
                            <td>
                                <input type="text" id="editGender" class="form-control" readonly>
                            </td>
                            <th>Last Assigned By</th>
                            <td><input type="text" id="editLastAssignedBy" class="form-control" readonly></td>
                        </tr>                                            
                        
                        <tr>
                            <th>Birthdate</th>
                            <td><input type="date" id="editBirthday" class="form-control" readonly></td>
                            <th>Date Last Updated</th>
                            <td><input type="date" id="editDateLastUpdated" class="form-control" readonly></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><input type="text" id="editStatus" class="form-control" readonly></td>
                            <th>Last Updated By</th>
                            <td><input type="text" id="editLastUpdatedBy" class="form-control" readonly></td>
                        </tr>
                        <tr id="rowDateSeparated">
                            <th id="thDateSeparated">Date Separated</th>
                            <td><input type="date" id="editDateSeparated" class="form-control"></td>
                            <th id="thDateReturned">Date Returned</th>
                            <td><input type="date" id="editDateReturn" class="form-control"></td>
                        </tr>

                        <tr id="rowStartDate">
                            <th id="thStartDate">Start</th>
                            <td><input type="date" id="editStartDate" class="form-control"></td>
                            <th id="thEndDate">End</th>
                            <td><input type="date" id="editEndDate" class="form-control"></td>
                        </tr>

                        <tr id="editRovingField" class="d-none">
                            <th>Roving Branches</th>
                            <td colspan="3">
                                <div id="editRovingContainer"></div>
                            </td>
                        </tr>
                        <tr id="editMultiBrandField" class="d-none">
                            <th>Multi Brands</th>
                            <td colspan="3">
                                <div id="editMultiBrandContainer"></div>
                            </td>
                        </tr>
                        <tr>
                            <th>Remarks</th>
                            <td colspan="3">
                                <input type="text" id="editRemarks" class="form-control" maxlength="100" placeholder="e.g. immediate resignation, pull out product">
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-3">
                    <h6 class="fw-bold mb-2">Update History</h6>

                    <div id="historyContainer" class="history-box">
                        <div class="text-muted small">No history available</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer d-flex justify-content-end">
                <button type="button" class="btn btn-primary" id="saveBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/history.js"></script>
<script src="assets/js/edit_promodizer.js"></script>
<script> 
document.addEventListener("DOMContentLoaded", function () {
    const reasonSelect = document.getElementById("editReasonUpdate");

    const thDateSeparated = document.querySelector("#rowDateSeparated th:nth-child(1)");
    const thDateReturned  = document.querySelector("#rowDateSeparated th:nth-child(3)");
    const thStartDate     = document.querySelector("#rowStartDate th:nth-child(1)");
    const thEndDate       = document.querySelector("#rowStartDate th:nth-child(3)");

    reasonSelect.addEventListener("change", function () {
        const value = this.value;

        // RESET DEFAULTS FIRST
        thDateSeparated.textContent = "Date Separated";
        thDateReturned.textContent  = "Date Returned";
        thStartDate.textContent     = "Start";
        thEndDate.textContent       = "End";

        // GROUP 1 → Effectivity Date
        const effectivityReasons = [
            "RESIGNED",
            "PULL-OUT / END OF CONTRACT",
            "BLOCKLISTED / AWOL / TERMINATED",
            "CHANGE SUB STATUS",
            "TRANSFER BRANCH",
        ];

        if (effectivityReasons.includes(value)) {
            thDateSeparated.textContent = "Effectivity Date";
        }

        // GROUP 2 → Leave (Start/End)
        const leaveReasons = [
            "EMERGENCY LEAVE",
            "MATERNITY LEAVE"
        ];

        if (leaveReasons.includes(value)) {
            thDateSeparated.textContent = "Start";
            thDateReturned.textContent  = "End";
        }

        // GROUP 3 → Transfer (Transfer Date)
        if (effectivityReasons.includes(value)) {
            thStartDate.textContent = "Effectivity Date";
        }
        
    });
});
</script>