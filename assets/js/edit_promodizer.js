const modalEl = document.getElementById('editPromodizerModal');

// Helper to clean null/nchar
function cleanValue(value) {
    if (!value) return '';
    const trimmed = value.toString().trim();
    return (trimmed.toLowerCase() === 'null' || trimmed === '') ? '' : trimmed;
}

// Toggle Date Separated
const reasonSelect = document.getElementById('editReasonUpdate');
const dateSeparatedRow = document.getElementById('rowDateSeparated');
const dateSeparatedInput = document.getElementById('editDateSeparated');

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
    const value = (reasonSelect.value || '').trim().toUpperCase();
    const shouldShow = showDateSeparatedReasons.includes(value);

    dateSeparatedRow.style.display = shouldShow ? "" : "none";
    dateSeparatedInput.disabled = !shouldShow;
    dateSeparatedInput.required = shouldShow; // ✅ ADDED: real validation control

    if (!shouldShow) {
        dateSeparatedInput.value = "";
    }
}

//Toggle Date Returned

const dateReturnedRow = document.getElementById('rowDateReturned');
const dateReturnedInput = document.getElementById('editDateReturn');

function toggleDateReturned() {
    const value = (reasonSelect.value || '').trim().toUpperCase();
    const shouldShow = value === "MATERNITY LEAVE";

    dateReturnedRow.style.display = shouldShow ? "" : "none";
    dateReturnedInput.disabled = !shouldShow;
    dateReturnedInput.required = shouldShow;

    if (!shouldShow) {
        dateReturnedInput.value = "";
    }
}


// Populate modal
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
                remarks: p.remarks,
                last_updated_by: p.last_updated_by,
                reason_update: p.reason_for_update,
                date_hired: p.date_hired,
                updated_at: p.updated_at
            };

            // Fill read-only fields
            document.getElementById('editPromodizerId').value = employee.id;
            document.getElementById('editFirstName').value = cleanValue(employee.first_name);
            document.getElementById('editLastName').value = cleanValue(employee.last_name);
            document.getElementById('editBranch').value = cleanValue(employee.branch);
            document.getElementById('editBrand').value = cleanValue(employee.brand);
            document.getElementById('editDateHired').value = employee.date_hired
                ? new Date(employee.date_hired).toLocaleDateString()
                : '-';
            document.getElementById('editStatus').value = cleanValue(employee.status) || '-';
            document.getElementById('editLastAssignedBy').value = cleanValue(employee.last_assigned_by);
            document.getElementById('editAssignmentDate').value = employee.assignment_date
                ? new Date(employee.assignment_date).toLocaleDateString()
                : '-';

            // Select fields
            const employmentSelect = document.getElementById('editEmploymentStatus');
            const empStatus = cleanValue(employee.employment_status).toUpperCase();
            employmentSelect.value = [...employmentSelect.options].some(opt => opt.value === empStatus)
                ? empStatus
                : '';

            const reasonValue = cleanValue(employee.reason_update).toUpperCase();
            reasonSelect.value = [...reasonSelect.options].some(opt => opt.value === reasonValue)
                ? reasonValue
                : '';

            // Editable fields
            document.getElementById('editDateSeparated').value = cleanValue(employee.date_separated);
            document.getElementById('editDateReturn').value = cleanValue(employee.date_of_return);
            document.getElementById('editRemarks').value = cleanValue(employee.remarks);

            // Read-only fields
            document.getElementById('editLastUpdatedBy').value = cleanValue(employee.last_updated_by);
            document.getElementById('editDateLastUpdated').value = employee.updated_at
                ? new Date(employee.updated_at).toISOString().split('T')[0]
                : '';

            // Enable only editable fields
            const editable = [
                'editEmploymentStatus',
                'editReasonUpdate',
                'editDateSeparated',
                'editDateReturn',
                'editRemarks'
            ];

            modalEl.querySelectorAll('input, select, textarea').forEach(el => {
                el.disabled = !editable.includes(el.id);
            });

            modal.show();

            // IMPORTANT: sync toggle after loading data
            toggleDateSeparated();
            toggleDateReturned();

        } catch (err) {
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load employee data' });
        }
    });
});

// Send form data
async function sendAction(data, actionName = 'Save') {
    const btns = modalEl.querySelectorAll('button');
    btns.forEach(b => b.disabled = true);

    try {
        const res = await fetch('functions/update_promodizer.php', {
            method: 'POST',
            body: data
        });

        const result = await res.json();

        if (result.status === 'success') {
            await Swal.fire({
                icon: 'success',
                title: `${actionName} Successful`,
                text: result.message
            });

            bootstrap.Modal.getInstance(modalEl).hide();
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: `${actionName} Failed`,
                text: result.message
            });
        }

    } catch (err) {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An unexpected error occurred.'
        });
    } finally {
        btns.forEach(b => b.disabled = false);
    }
}

// Save button
document.getElementById('saveBtn').addEventListener('click', async () => {
    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Save Changes?',
        text: 'Changes to this employee may affect assignments and history. This cannot be easily undone.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Save Changes',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3085d6',
        reverseButtons: true
    });

    if (!confirm.isConfirmed) return;

    const reason = (document.getElementById('editReasonUpdate').value || '').toUpperCase();

    const dateSeparated = document.getElementById('editDateSeparated');
    const dateReturned = document.getElementById('editDateReturn');

    // =========================
    // VALIDATION RULES
    // =========================

    if (dateSeparated.required && !dateSeparated.value) {
        return Swal.fire({
            icon: 'warning',
            title: 'Date Separated Required',
            text: 'Please enter Date Separated for this status.'
        });
    }

    if (reason === "MATERNITY LEAVE" && !dateReturned.value) {
        return Swal.fire({
            icon: 'warning',
            title: 'Date Returned Required',
            text: 'Please enter Date Returned for maternity leave.'
        });
    }

    const formData = new FormData();
    formData.set('id', document.getElementById('editPromodizerId').value);
    formData.set('employment_status', document.getElementById('editEmploymentStatus').value);
    formData.set('reason_update', reason);
    formData.set('date_separated', dateSeparated.value);
    formData.set('date_returned', dateReturned.value);
    formData.set('last_updated_by', document.getElementById('editLastUpdatedBy').value);
    formData.set('date_last_updated', document.getElementById('editDateLastUpdated').value);
    formData.set('remarks', document.getElementById('editRemarks').value);

    sendAction(formData, 'Save Changes');
});

// Init toggle listener
document.addEventListener('DOMContentLoaded', function () {
    if (reasonSelect) {
        reasonSelect.addEventListener('change', toggleDateSeparated);
        reasonSelect.addEventListener('change', toggleDateReturned);
    }
});