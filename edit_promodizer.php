<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

$employeeId = $_GET['id'] ?? null;
if (!$employeeId) {
    header('Location: promodizers.php');
    exit;
}
?>

<style>
#editPromodizerPage .form-control:not([readonly]):not([disabled]),
#editPromodizerPage .form-select:not([disabled]) {
    background-color: #fffbdf !important;
    opacity: 1;
}

#editPromodizerPage .form-control[readonly],
#editPromodizerPage .form-control[disabled],
#editPromodizerPage .form-select[disabled] {
    background-color: #e9ecef !important;
    opacity: 1;
    cursor: not-allowed;
}

#editPromodizerPage .history-box {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 10px;
    background: #fafafa;
}

.history-item {
    padding: 6px 8px;
    border-bottom: 1px solid #eee;
}

.history-item:last-child {
    border-bottom: none;
}

.history-date {
    font-size: 12px;
    color: #888;
}

.history-reason {
    font-weight: 600;
    font-size: 14px;
    color: #333;
}

th {
    vertical-align: middle;
}

.remarks-input::placeholder {
    color: #cccccc;
    opacity: 1;
}

.reason-select {
    color: #b0b0b0;
}

.reason-select:valid {
    color: #212529;
}

.reason-select option {
    color: #212529;
}

.reason-select option[value=""] {
    color: #b0b0b0;
}

.date-input {
    color: #000;
}

.date-input:invalid {
    color: #b0b0b0;
}

.date-input:disabled {
    color: #212529;
}

#printPdfModal .form-control:not([readonly]):not([disabled]) {
    background-color: #fffbdf !important;
    opacity: 1;
}

#printPdfModal .form-control[readonly],
#printPdfModal .form-control[disabled] {
    background-color: #e9ecef !important;
    opacity: 1;
    cursor: not-allowed;
}

#printPdfModal #recipientName,
#printPdfModal #recipientPosition {
    text-transform: uppercase;
}

#printPdfModal .recipient-name-multi,
#printPdfModal .recipient-position-multi {
    text-transform: uppercase;
}

#editReasonUpdate option:disabled {
    color: #adb5bd;
}

/* Employee picture */
#editEmployeePictureBox {
    width: 130px;
    height: 130px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background-color: #f1f1f1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

#editEmployeePictureBox img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

#editEmployeePictureBox .no-picture-label {
    font-size: 12px;
    color: #999;
    text-align: center;
    padding: 4px;
}
</style>

<div class="content">
    <div class="container-fluid" id="editPromodizerPage">

        <div class="row mb-3 align-items-center">
            <div class="col-md-6">
                <h4 class="fw-bold mb-0">Employee Master Data</h4>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <button type="button" class="btn btn-secondary" id="backBtn">
                    &larr; Back
                </button>
                <div id="editAlert"></div>
                <input type="hidden" id="editPromodizerId">
                <input type="hidden" id="editEmployeeId">
                <input type="hidden" id="editLoaCode">

                <!-- Reason for Update -->
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Reason for Update</label>
                        <select id="editReasonUpdate" class="form-select reason-select" required>
                            <option value="" disabled selected>-- Select Reason --</option>
                            <option value="ADD BRANCH/BRAND">ADD BRANCH/BRAND</option>
                            <option value="BLACKLISTED / AWOL / TERMINATED">BLACKLISTED / AWOL / TERMINATED</option>
                            <option value="CHANGE AGENCY">CHANGE AGENCY</option>
                            <option value="CHANGE EMPLOYMENT STATUS">CHANGE EMPLOYMENT STATUS</option>
                            <option value="CHANGE SUB STATUS">CHANGE SUB STATUS</option>
                            <option value="CLERICAL ERROR">CLERICAL ERROR</option>
                            <option value="DECEASED">DECEASED</option>
                            <option value="EMERGENCY LEAVE">EMERGENCY LEAVE</option>
                            <option value="MATERNITY LEAVE">MATERNITY LEAVE</option>
                            <option value="PULL-OUT / END OF CONTRACT">PULL-OUT / END OF CONTRACT</option>
                            <option value="REMOVE BRANCH/BRAND">REMOVE CURRENT BRANCH/BRAND</option>
                            <option value="RESIGNED">RESIGNED</option>
                            <option value="TRANSFER BRANCH">TRANSFER BRANCH</option>
                            <option value="UPDATE ADDRESS">UPDATE ADDRESS</option>
                            <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin')): ?>
                                <option value="UPDATE BIOMETRIC NUMBER">UPDATE BIOMETRIC NUMBER</option>
                            <?php endif; ?>
                            <option value="UPDATE CONTACT NUMBER">UPDATE CONTACT NUMBER</option>
                            <option value="UPDATE MARITAL STATUS">UPDATE MARITAL STATUS</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <hr>
                    <H3 class="fw-bold mb-2">Personal Details</H6>
                </div>

                <!-- Picture + Names -->
                <div class="row g-3 mb-3">
                    <div class="col-md-2 d-flex align-items-start">
                        <div id="editEmployeePictureBox">
                            <div class="no-picture-label">No Photo</div>
                        </div>
                    </div>
                    <div class="col-md-10">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">First Name</label>
                                <input type="text" id="editFirstName" class="form-control" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" id="editMiddleName" class="form-control" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" id="editLastName" class="form-control" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Suffix</label>
                                <input type="text" id="editSuffix" class="form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal details -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Gender</label>
                        <input type="text" id="editGender" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Marital Status</label>
                        <select id="editMaritalStatus" class="form-select" disabled>
                            <option value="" disabled selected>Select Marital Status</option>
                            <option value="SINGLE">SINGLE</option>
                            <option value="MARRIED">MARRIED</option>
                            <option value="WIDOWED">WIDOWED</option>
                            <option value="SEPARATED">SEPARATED</option>
                            <option value="DIVORCED">DIVORCED</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Birthdate</label>
                        <input type="date" id="editBirthday" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" id="editContactNumber" class="form-control" maxlength="11" inputmode="numeric" disabled>
                    </div>
                </div>

                <!-- Address -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Province</label>
                        <select id="editProvince" name="province" class="form-select" disabled>
                            <option value="" disabled selected>-- Select Province --</option>
                        </select>
                        <input type="hidden" id="editProvinceName" name="province_name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Municipality</label>
                        <select id="editMunicipality" name="municipality" class="form-select" disabled>
                            <option value="" disabled selected>-- Select Municipality --</option>
                        </select>
                        <input type="hidden" id="editMunicipalityName" name="municipality_name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Barangay</label>
                        <select id="editBarangay" name="barangay" class="form-select" disabled>
                            <option value="" disabled selected>-- Select Barangay --</option>
                        </select>
                        <input type="hidden" id="editBarangayName" name="barangay_name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Street</label>
                        <input type="text" id="editStreet" name="street" class="form-control" disabled style="text-transform: uppercase;">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <hr>
                    <H3 class="fw-bold mb-2">Employment Details</H6>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Biometric Number <small class="text-muted">(optional)</small></label>
                        <input type="text" id="editBiometricNumber" class="form-control" maxlength="7" inputmode="numeric" placeholder="e.g. 1234567" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Designated Categories</label>
                        <input type="text" id="editCategories" class="form-control" readonly>
                    </div>
                </div>

                <!-- Assignment classification -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Branch</label>
                        <select id="editBranch" class="form-control"></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Brand</label>
                        <select id="editBrand" class="form-control"></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date Hired</label>
                        <input type="date" id="editDateHired" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <input type="text" id="editStatus" class="form-control" readonly>
                    </div>
                </div>                

                <!-- Statuses and Dates - all in one row, all equal width -->
                
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Employment Status</label>
                        <select id="editEmploymentStatus" class="form-select">
                            <option value="PERMANENT">PERMANENT</option>
                            <option value="RELIEVER">RELIEVER</option>
                            <option value="SEASONAL">SEASONAL</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sub-Status</label>
                        <select id="editSubStatus" class="form-select">
                            <option value="STATIONARY">STATIONARY</option>
                            <option value="MULTI BRANCH">MULTI BRANCH</option>
                            <option value="MULTI BRAND">MULTI BRAND</option>
                            <option value="HYBRID">HYBRID</option>
                        </select>
                    </div>

                    <!-- Conditional: separation / return -->
                    <div class="col-md-3 date-separated-group">
                        <label class="form-label" id="thDateSeparated">Date Separated</label>
                        <input type="date" id="editDateSeparated" class="form-control date-input required">
                    </div>
                    <div class="col-md-3 date-separated-group">
                        <label class="form-label" id="thDateReturned">Date Returned</label>
                        <input type="date" id="editDateReturn" class="form-control date-input required">
                    </div>

                    <!-- Conditional: start / end -->
                    <div class="col-md-3 date-start-group">
                        <label class="form-label" id="thStartDate">Start</label>
                        <input type="date" id="editStartDate" class="form-control date-input required">
                    </div>
                    <div class="col-md-3 date-start-group">
                        <label class="form-label" id="thEndDate">End</label>
                        <input type="date" id="editEndDate" class="form-control date-input required">
                    </div>
                </div>

                <!-- Agency -->
                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Agency</label>
                        <select id="editAgency" class="form-select"></select>
                    </div>
                </div>

                <!-- Update meta -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Assignment Date</label>
                        <input type="text" id="editAssignmentDate" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Last Assigned By</label>
                        <input type="text" id="editLastAssignedBy" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date Last Updated</label>
                        <input type="date" id="editDateLastUpdated" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Last Updated By</label>
                        <input type="text" id="editLastUpdatedBy" class="form-control" readonly>
                    </div>                    
                </div>

                <!-- Roving branches / Multi brands -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6 d-none" id="editRovingField">
                        <label class="form-label">Roving Branches</label>
                        <div id="editRovingContainer"></div>
                    </div>
                    <div class="col-md-6 d-none" id="editMultiBrandField">
                        <label class="form-label">Multi Brands</label>
                        <div id="editMultiBrandContainer"></div>
                    </div>
                </div>

                <!-- Remarks -->
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <input type="text" id="editRemarks" class="form-control remarks-input" maxlength="100" placeholder="e.g. immediate resignation, pull out product">
                    </div>
                </div>

                <div class="mt-3">
                    <h6 class="fw-bold mb-2">Update History</h6>
                    <div id="historyContainer" class="history-box">
                        <div class="text-muted small">No history available</div>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <div>                    
                    <button type="button" class="btn btn-danger" id="openPrintModalBtn">
                        Print LOA
                    </button>
                </div>
                <button type="button" class="btn btn-primary" id="saveBtn">
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PRINT LOA MODAL (kept as modal) -->
<div class="modal fade" id="printPdfModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Letter of Advice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="singleRecipientFields">
                    <div class="mb-3">
                        <label class="form-label">Recipient Full Name</label>
                        <input type="text" id="recipientName" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" id="recipientPosition" class="form-control">
                    </div>
                </div>

                <div id="multiRecipientFields" class="d-none"></div>

                <div class="mb-3">
                    <label class="form-label" for="loaDateHired">Date Hired</label>
                    <input type="date" id="loaDateHired" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">End Date <small>(optional)</small></label>
                    <input type="date" id="recipientEndDate" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="generatePdfBtn">Generate LOA</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.userRole = "<?= $_SESSION['role'] ?? '' ?>";
    window.editEmployeeRecordId = "<?= htmlspecialchars($employeeId, ENT_QUOTES) ?>";
</script>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/employee/edit_employee/history.js"></script>
<script src="assets/js/employee/edit_employee/edit_promodizer.js"></script>
<script src="assets/js/employee/edit_employee/pdf.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    // ============================================================
    // BACK BUTTON — return to promodizer list with filters restored
    // ============================================================
    const backBtn = document.getElementById("backBtn");
    if (backBtn) {
        backBtn.addEventListener("click", function () {
            window.location.href = "promodizers.php?restore=1";
        });
    }

    // ============================================================
    // EMPLOYEE PICTURE — fetch and display (read-only)
    // check_employee_picture.php keys off the actual employee_id
    // (e.g. "EMP-..."), not the promodizer record id in the URL —
    // so this is exposed as a function and called from
    // edit_promodizer.js once employee.employee_id is known
    // (see loadEmployeePage()).
    // ============================================================
    window.loadEmployeePicture = function (employeeId) {
        const box = document.getElementById("editEmployeePictureBox");
        if (!box) return;

        if (!employeeId) {
            box.innerHTML = '<div class="no-picture-label">No Photo</div>';
            return;
        }

        fetch("functions/check_employee_picture.php?employee_id=" + encodeURIComponent(employeeId))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.exists && data.picture_data) {
                    box.innerHTML = "";
                    const img = document.createElement("img");
                    img.src = data.picture_data;
                    img.alt = "Employee Photo";
                    box.appendChild(img);
                } else {
                    box.innerHTML = '<div class="no-picture-label">No Photo</div>';
                }
            })
            .catch(function (err) {
                console.error("Failed to load employee picture:", err);
                box.innerHTML = '<div class="no-picture-label">No Photo</div>';
            });
    };

    // ============================================================
    // REASON / HEADER TOGGLE LOGIC
    // Also toggles visibility of the date-separated-group vs
    // date-start-group fields based on the selected reason.
    // ============================================================
    const reasonSelect = document.getElementById("editReasonUpdate");
    const employmentStatusSelect = document.getElementById("editEmploymentStatus");

    const thDateSeparated = document.getElementById("thDateSeparated");
    const thDateReturned  = document.getElementById("thDateReturned");
    const thStartDate     = document.getElementById("thStartDate");
    const thEndDate       = document.getElementById("thEndDate");

    const dateSeparatedGroup = document.querySelectorAll(".date-separated-group");
    const dateStartGroup     = document.querySelectorAll(".date-start-group");

    function updateHeaders() {
        const value = reasonSelect.value;

        thDateSeparated.textContent = "Date Separated";
        thDateReturned.textContent  = "Date Returned";
        thStartDate.textContent     = "Start";
        thEndDate.textContent       = "End";

        const effectivityReasons = [
            "RESIGNED",
            "PULL-OUT / END OF CONTRACT",
            "BLACKLISTED / AWOL / TERMINATED",
            "DECEASED",
            "TRANSFER BRANCH",
            "REMOVE BRANCH/BRAND",
        ];

        const leaveReasons = ["EMERGENCY LEAVE", "MATERNITY LEAVE"];

        if (effectivityReasons.includes(value)) {
            thDateSeparated.textContent = "Effectivity Date";
            thStartDate.textContent = "Effectivity Date";
        }

        if (leaveReasons.includes(value)) {
            thDateSeparated.textContent = "Start";
            thDateReturned.textContent  = "End";
        }

        if (employmentStatusSelect.value === "PERMANENT") {
            thStartDate.textContent = "Effectivity Date";
        }

        // Toggle which date group is visible.
        // NOTE: adjust this condition to match your actual business
        // rule for which reasons use "separated/return" vs "start/end".
        if (effectivityReasons.includes(value) || leaveReasons.includes(value)) {
            dateSeparatedGroup.forEach(el => el.classList.remove("d-none"));
            dateStartGroup.forEach(el => el.classList.add("d-none"));
        } else {
            dateSeparatedGroup.forEach(el => el.classList.add("d-none"));
            dateStartGroup.forEach(el => el.classList.remove("d-none"));
        }
    }

    reasonSelect.addEventListener("change", updateHeaders);
    employmentStatusSelect.addEventListener("change", updateHeaders);
    updateHeaders();


    // ============================================================
    // ADDRESS FIELD HOVER-TOOLTIP LOGIC
    // Shows full value on hover for Province, Municipality,
    // Barangay, and Street — useful when text is too long
    // to display fully inside the input.
    // ============================================================
    const addressFieldIds = [
        "editProvince",
        "editMunicipality",
        "editBarangay",
        "editStreet"
    ];

    function setAddressTooltip(el) {
        if (!el) return;
        if (el.tagName === "SELECT") {
            const selectedOption = el.options[el.selectedIndex];
            el.title = selectedOption ? selectedOption.text : "";
        } else {
            el.title = el.value || "";
        }
    }

    function refreshAddressTooltips() {
        addressFieldIds.forEach(function (id) {
            setAddressTooltip(document.getElementById(id));
        });
    }

    // Live update as values change
    addressFieldIds.forEach(function (id) {
        const el = document.getElementById(id);
        if (!el) return;

        if (el.tagName === "SELECT") {
            el.addEventListener("change", function () { setAddressTooltip(el); });
        } else {
            el.addEventListener("input", function () { setAddressTooltip(el); });
        }
    });

    // Run once on load in case fields are pre-filled server-side
    refreshAddressTooltips();

    // Poll every 500ms to catch values set programmatically
    // (e.g. jQuery .val() from an AJAX response), since those
    // don't fire native "input"/"change" events.
    setInterval(refreshAddressTooltips, 500);

    // Expose globally so edit_promodizer.js can call this after
    // populating the form fields via AJAX
    window.refreshAddressTooltips = refreshAddressTooltips;

});
</script>

<?php include 'modals/change_password_modal.php'; ?>