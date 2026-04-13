const modalEl = document.getElementById('editPromodizerModal');

// Helper to clean null/nchar
function cleanValue(value) {
    if (!value) return '';
    const trimmed = value.toString().trim();
    return (trimmed.toLowerCase() === 'null' || trimmed === '') ? '' : trimmed;
}

// =========================
// ELEMENT SAFETY CHECKS
// =========================
const reasonSelect = document.getElementById('editReasonUpdate');
const dateSeparatedRow = document.getElementById('rowDateSeparated');
const dateSeparatedInput = document.getElementById('editDateSeparated');

const dateReturnedRow = document.getElementById('rowDateReturned');
const dateReturnedInput = document.getElementById('editDateReturn');

// Guard (prevents crashes if modal DOM not loaded yet)
if (!reasonSelect || !dateSeparatedInput || !dateReturnedInput) {
    console.error('Modal elements not found. Check modal HTML.');
}

// =========================
// TOGGLE DATE SEPARATED
// =========================
const showDateSeparatedReasons = [
    "RESIGNED",
    "PULL-OUT / TERMINATED",
    "AWOL",
    "RETRENCHMENT",
    "END OF CONTRACT",
    "BLACKLISTED",
    "TRANSFER",
    "MATERNITY LEAVE"
];

function toggleDateSeparated() {
    if (!reasonSelect) return;

    const value = (reasonSelect.value || '').trim().toUpperCase();
    const shouldShow = showDateSeparatedReasons.includes(value);

    if (dateSeparatedRow) dateSeparatedRow.style.display = shouldShow ? "" : "none";

    if (dateSeparatedInput) {
        dateSeparatedInput.disabled = !shouldShow;
        dateSeparatedInput.required = shouldShow;

        if (!shouldShow) dateSeparatedInput.value = '';
    }
}

// =========================
// TOGGLE DATE RETURNED
// =========================
function toggleDateReturned() {
    if (!reasonSelect) return;

    const value = (reasonSelect.value || '').trim().toUpperCase();
    const shouldShow = value === "MATERNITY LEAVE";

    if (dateReturnedRow) dateReturnedRow.style.display = shouldShow ? "" : "none";

    if (dateReturnedInput) {
        dateReturnedInput.disabled = !shouldShow;
        dateReturnedInput.required = shouldShow;

        if (!shouldShow) dateReturnedInput.value = '';
    }
}

// =========================
// POPULATE MODAL
// =========================
document.querySelectorAll('.clickable-row').forEach(row => {
    row.addEventListener('click', async () => {
        const id = row.dataset.id;
        const modal = new bootstrap.Modal(modalEl);

        try {
            const res = await fetch(`functions/get_employee.php?id=${id}`);
            const p = await res.json();

            if (!p || !p.id) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Employee not found' });
                return;
            }

            const employee = {
                id: p.id,
                first_name: p.first_name,
                last_name: p.last_name,
                branch: p.branch,
                brand: p.brand,
                assignment_date: p.assignment_date,
                last_assigned_by: p.last_assigned_by,
                status: p.status,
                date_of_return: p.date_of_return,
                date_separated: p.date_separated,
                employment_status: p.employment_status,
                sub_status: p.sub_status, // ✅ FIXED (was missing)
                remarks: p.remarks,
                last_updated_by: p.last_updated_by,
                reason_update: p.reason_for_update,
                date_hired: p.date_hired,
                updated_at: p.updated_at
            };

            // =========================
            // SAFE FIELD ASSIGNMENTS
            // =========================
            const el = (id) => document.getElementById(id);

            if (el('editPromodizerId')) el('editPromodizerId').value = employee.id;
            if (el('editFirstName')) el('editFirstName').value = cleanValue(employee.first_name);
            if (el('editLastName')) el('editLastName').value = cleanValue(employee.last_name);
            if (el('editBranch')) el('editBranch').value = cleanValue(employee.branch);
            if (el('editBrand')) el('editBrand').value = cleanValue(employee.brand);

            // ✅ FIXED DATE HANDLING (NO "-")
            if (el('editDateHired')) {
                el('editDateHired').value = employee.date_hired || '';
            }

            if (el('editStatus')) el('editStatus').value = cleanValue(employee.status) || '-';
            if (el('editLastAssignedBy')) el('editLastAssignedBy').value = cleanValue(employee.last_assigned_by);

            if (el('editAssignmentDate')) {
                el('editAssignmentDate').value = employee.assignment_date || '';
            }

            // =========================
            // SELECT FIELDS SAFE
            // =========================
            const employmentSelect = el('editEmploymentStatus');
            if (employmentSelect) {
                const empStatus = cleanValue(employee.employment_status).toUpperCase();
                employmentSelect.value = [...employmentSelect.options].some(opt => opt.value === empStatus)
                    ? empStatus
                    : '';
            }

            const subStatusSelect = el('editSubStatus');
            if (subStatusSelect) {
                const subStatus = cleanValue(employee.sub_status).toUpperCase();
                const valid = [...subStatusSelect.options].some(opt => opt.value === subStatus);
                subStatusSelect.value = valid ? subStatus : '';
            }

            if (reasonSelect) {
                const reasonValue = cleanValue(employee.reason_update).toUpperCase();
                reasonSelect.value = [...reasonSelect.options].some(opt => opt.value === reasonValue)
                    ? reasonValue
                    : '';
            }

            // =========================
            // EDITABLE FIELDS SAFE
            // =========================
            if (el('editDateSeparated')) el('editDateSeparated').value = cleanValue(employee.date_separated);
            if (el('editDateReturn')) el('editDateReturn').value = cleanValue(employee.date_of_return);
            if (el('editRemarks')) el('editRemarks').value = cleanValue(employee.remarks);

            // =========================
            // READ ONLY
            // =========================
            if (el('editLastUpdatedBy')) el('editLastUpdatedBy').value = cleanValue(employee.last_updated_by);
            if (el('editDateLastUpdated')) {
                el('editDateLastUpdated').value = employee.updated_at
                    ? employee.updated_at.split(' ')[0]
                    : '';
            }

            // =========================
            // DISABLE LOGIC
            // =========================
            const editable = [
                'editEmploymentStatus',
                'editSubStatus',
                'editReasonUpdate',
                'editDateSeparated',
                'editDateReturn',
                'editRemarks'
            ];

            modalEl.querySelectorAll('input, select, textarea').forEach(el => {
                el.disabled = !editable.includes(el.id);
            });

            modal.show();

            // sync toggles
            toggleDateSeparated();
            toggleDateReturned();

        } catch (err) {
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load employee data' });
        }
    });
});

// =========================
// SAVE BUTTON (UNCHANGED LOGIC)
// =========================
document.getElementById('saveBtn').addEventListener('click', async () => {

    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Save Changes?',
        text: 'Changes to this employee may affect assignments and history. This cannot be easily undone.',
        showCancelButton: true
    });

    if (!confirm.isConfirmed) return;

    const reason = (document.getElementById('editReasonUpdate').value || '').toUpperCase();

    const dateSeparated = document.getElementById('editDateSeparated');
    const dateReturned = document.getElementById('editDateReturn');

    if (dateSeparated?.required && !dateSeparated.value) {
        return Swal.fire({
            icon: 'warning',
            title: 'Date Separated Required'
        });
    }

    if (reason === "MATERNITY LEAVE" && !dateReturned.value) {
        return Swal.fire({
            icon: 'warning',
            title: 'Date Returned Required'
        });
    }

    const formData = new FormData();
    formData.set('id', document.getElementById('editPromodizerId').value);
    formData.set('employment_status', document.getElementById('editEmploymentStatus').value);
    formData.set('sub_status', document.getElementById('editSubStatus').value);
    formData.set('reason_update', reason);
    formData.set('date_separated', dateSeparated.value);
    formData.set('date_returned', dateReturned.value);
    formData.set('last_updated_by', document.getElementById('editLastUpdatedBy').value);
    formData.set('date_last_updated', document.getElementById('editDateLastUpdated').value);
    formData.set('remarks', document.getElementById('editRemarks').value);

    sendAction(formData, 'Save Changes');
});

// =========================
// INIT LISTENERS
// =========================
document.addEventListener('DOMContentLoaded', function () {
    if (reasonSelect) {
        reasonSelect.addEventListener('change', toggleDateSeparated);
        reasonSelect.addEventListener('change', toggleDateReturned);
    }
});