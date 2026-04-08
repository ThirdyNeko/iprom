// Edit Promodizer JS
const modalEl = document.getElementById('editPromodizerModal');

// Populate modal when a row is clicked
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

            // Map database columns to JS properties
            const employee = {
                id: p.id,
                first_name: p.first_name,
                last_name: p.last_name,
                branch: p.branch,
                brand: p.brand,
                assignment_date: p.assignment_date,
                last_assigned_by: p.last_assigned_by,
                status: p.status,
                created_at: p.created_at,
                updated_at: p.updated_at,
                date_of_return: p.date_of_return,
                date_separated: p.date_separated,
                employment_status: p.employment_status,
                remarks: p.remarks,
                last_updated_by: p.last_updated_by,
                reason_update: p.reason_for_update,
                date_hired: p.date_hired,
                start_date: p.start_date,
                end_date: p.end_date
            };

            // Helper function to clean null/nchar values
            function cleanValue(value) {
                if (!value) return '';
                const trimmed = value.toString().trim();
                return (trimmed.toLowerCase() === 'null' || trimmed === '') ? '' : trimmed;
            }

            // Fill read-only fields
            document.getElementById('editPromodizerId').value = employee.id;
            document.getElementById('editFirstName').value = cleanValue(employee.first_name);
            document.getElementById('editLastName').value = cleanValue(employee.last_name);
            document.getElementById('editBranch').value = cleanValue(employee.branch);
            document.getElementById('editBrand').value = cleanValue(employee.brand);
            document.getElementById('editDateHired').value = employee.date_hired ? new Date(employee.date_hired).toLocaleDateString() : '-';
            document.getElementById('editStatus').textContent = cleanValue(employee.status) || '-';
            document.getElementById('editLastAssignedBy').value = cleanValue(employee.last_assigned_by);
            document.getElementById('editAssignmentDate').value = employee.assignment_date ? new Date(employee.assignment_date).toLocaleDateString() : '-';

            // Fill editable selects safely
            const employmentSelect = document.getElementById('editEmploymentStatus');
            const empStatus = cleanValue(employee.employment_status).toUpperCase();
            employmentSelect.value = [...employmentSelect.options].some(opt => opt.value === empStatus) ? empStatus : '';

            const reasonSelect = document.getElementById('editReasonUpdate');
            const reasonValue = cleanValue(employee.reason_update).toUpperCase();
            reasonSelect.value = [...reasonSelect.options].some(opt => opt.value === reasonValue) ? reasonValue : '';

            // Fill other editable fields
            document.getElementById('editDateSeparated').value = cleanValue(employee.date_separated);
            document.getElementById('editDateReturn').value = cleanValue(employee.date_of_return);
            document.getElementById('editLastUpdatedBy').value = cleanValue(employee.last_updated_by);
            document.getElementById('editDateLastUpdated').value = employee.updated_at ? new Date(employee.updated_at).toISOString().split('T')[0] : '';
            document.getElementById('editRemarks').value = cleanValue(employee.remarks);

            // Enable only editable fields
            const inputs = modalEl.querySelectorAll('input, select, textarea');
            const editable = [
                'editEmploymentStatus',
                'editReasonUpdate',
                'editDateSeparated',
                'editDateReturn',
                'editLastUpdatedBy',
                'editDateLastUpdated',
                'editRemarks'
            ];
            inputs.forEach(el => el.disabled = !editable.includes(el.id));

            modal.show();

        } catch(err) {
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load employee data' });
        }
    });
});

// Helper AJAX function to send form data
async function sendAction(data, actionName = 'Save') {
    const btns = modalEl.querySelectorAll('button');
    btns.forEach(b => b.disabled = true);

    try {
        const res = await fetch('functions/update_promodizer.php', { method: 'POST', body: data });
        const result = await res.json();

        if(result.status === 'success') {
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
    } catch(err) {
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

// Save Changes button
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

    const formData = new FormData();
    formData.set('id', document.getElementById('editPromodizerId').value);
    formData.set('employment_status', document.getElementById('editEmploymentStatus').value);
    formData.set('reason_update', document.getElementById('editReasonUpdate').value);
    formData.set('date_separated', document.getElementById('editDateSeparated').value);
    formData.set('date_returned', document.getElementById('editDateReturn').value);
    formData.set('last_updated_by', document.getElementById('editLastUpdatedBy').value);
    formData.set('date_last_updated', document.getElementById('editDateLastUpdated').value);
    formData.set('remarks', document.getElementById('editRemarks').value);

    sendAction(formData, 'Save Changes');
});