document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addEmployeeForm');
    const btn = form.querySelector('button[type="submit"]');
    const employmentStatus = document.getElementById('employmentStatus');
    const dateRangeFields = document.getElementById('dateRangeFields');
    const rovingField = document.getElementById('rovingField');
    const rovingContainer = document.getElementById('rovingContainer');
    const remarks = form.querySelector('textarea[name="remarks"]');
    const remarksCount = document.getElementById('remarksCount');
    
    const mainBranchSelect = form.querySelector('select[name="branch"]');
    const mainBrandSelect  = form.querySelector('select[name="brand"]');
    // =========================
    // Convert all text inputs and select values to uppercase
    // =========================
    form.querySelectorAll('input[type="text"]').forEach(input => {
        input.addEventListener('input', () => {
            input.value = input.value.toUpperCase();
        });
    });

    form.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', () => {
            if (select.value) select.value = select.value.toUpperCase();
        });
    });
    // =========================
    // Toggle fields based on Employment Status
    // =========================
    employmentStatus.addEventListener('change', toggleFields);
    function toggleFields() {
        const value = this.value;

        // Hide optional sections first
        dateRangeFields.classList.add('d-none');
        rovingField.classList.add('d-none');

        // Remove required attributes
        dateRangeFields.querySelectorAll('input').forEach(i => i.required = false);
        rovingField.querySelectorAll('.roving-select').forEach(s => s.required = false);

        if (value === 'SEASONAL' || value === 'RELIEVER') {
            dateRangeFields.classList.remove('d-none');
            dateRangeFields.querySelectorAll('input').forEach(i => i.required = true);
        } else if (value === 'ROVING') {
            rovingField.classList.remove('d-none');
            rovingField.querySelectorAll('.roving-select').forEach(s => s.required = true);
        }
    }

    // =========================
    // Add / Remove roving branch rows
    // =========================
    rovingContainer.addEventListener('click', function(e) {
        const row = e.target.closest('.roving-row');
        if (!row) return;

        if (e.target.classList.contains('add-branch')) {
            const clone = row.cloneNode(true);
            clone.querySelector('select').value = '';
            rovingContainer.appendChild(clone);
        }

        if (e.target.classList.contains('remove-branch')) {
            const rows = rovingContainer.querySelectorAll('.roving-row');
            if (rows.length > 1) {
                row.remove();
            } else {
                row.querySelector('select').value = '';
            }
        }
    });

    // =========================
    // Remarks character count
    // =========================
    remarks.addEventListener('input', function() {
        remarksCount.textContent = `${this.value.length} / 100`;
    });

    // =========================
    // Form submit
    // =========================
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const branch = mainBranchSelect.value || '';
        const brand  = mainBrandSelect.value || '';
        const statusType = employmentStatus.value;

        // Handle start_date and end_date explicitly
        const startDateInput = form.querySelector('input[name="start_date"]');
        const endDateInput   = form.querySelector('input[name="end_date"]');

        if (startDateInput && startDateInput.value) {
            formData.set('start_date', startDateInput.value);
        } else {
            formData.delete('start_date');
        }

        if (endDateInput && endDateInput.value) {
            formData.set('end_date', endDateInput.value);
        } else {
            formData.delete('end_date');
        }

        // Check required fields
        const requiredFields = form.querySelectorAll('[required]');
        for (let field of requiredFields) {
            if (field.offsetParent !== null && !field.value) {
                return Swal.fire('Missing Field', 'Please fill in all required fields.', 'warning');
            }
        }

        // Validate dates
        if (statusType === 'SEASONAL' || statusType === 'RELIEVER') {
            if (!startDateInput.value || !endDateInput.value) {
                return Swal.fire('Missing Dates', 'Start and End dates are required.', 'warning');
            }
            if (new Date(startDateInput.value) > new Date(endDateInput.value)) {
                return Swal.fire('Invalid Dates', 'End date must be after start date.', 'error');
            }
        }

        // Validate roving branches
        if (statusType === 'ROVING') {
            const branches = Array.from(rovingContainer.querySelectorAll('.roving-select')).map(s => s.value);
            if (branches.includes('') || new Set(branches).size !== branches.length) {
                const msg = branches.includes('') ? 'Please select all roving branches.' : 'Duplicate branches are not allowed.';
                return Swal.fire('Roving Branch Error', msg, 'error');
            }
            // Append each branch as a separate form field
            formData.delete('roving_branches');
            branches.forEach(b => formData.append('roving_branches[]', b));
        }

        // Confirmation
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Are you sure?',
            text: 'This action cannot be easily changed once saved.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Save',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
            reverseButtons: true
        });
        if (!confirm.isConfirmed) return;

        try {
            btn.disabled = true;

            // Check assignment for main + roving branches
            const branchesToCheck = [];

            // Always add main branch if selected
            if (branch) {
                branchesToCheck.push(branch);
            }

            // Add roving branches if any
            if (statusType === 'ROVING') {
                const rovingBranches = Array.from(rovingContainer.querySelectorAll('.roving-select')).map(s => s.value);
                branchesToCheck.push(...rovingBranches);
            }

            // Remove duplicates just in case main branch is also selected in roving
            const uniqueBranches = [...new Set(branchesToCheck)];

            // Now check each branch against the brand
            for (let b of uniqueBranches) {
                const res = await fetch('functions/check_assignment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ branch: b, brand })
                });
                const data = await res.json();
                if (!data.exists) {
                    return Swal.fire(
                        'Invalid Selection',
                        `No assignment exists for the selected Branch & Brand: ${b} & ${brand}.`,
                        'error'
                    );
                }
            }

            // Set status
            const status = (statusType === 'ROVING' || (branch && brand)) ? 'ACTIVE' : 'INACTIVE';
            formData.set('status', status);
            formData.set('employment_status', statusType);
            formData.set('assigned_by', window.currentUser || 'SYSTEM');

            // Submit
            const submitRes = await fetch('functions/add_employee.php', { method: 'POST', body: formData });
            const submitData = await submitRes.json();

            if (submitData.status === 'success') {
                Swal.fire('Employee Added!', submitData.message, 'success').then(() => {
                    form.reset();
                    dateRangeFields.classList.add('d-none');
                    rovingField.classList.add('d-none');
                    remarksCount.textContent = '0 / 100';
                    rovingContainer.querySelectorAll('.roving-row').forEach((row, idx) => { if (idx>0) row.remove(); });
                    rovingContainer.querySelector('select').value = '';
                    bootstrap.Modal.getInstance(document.getElementById('addEmployeeModal')).hide();
                    location.reload();
                });
            } else {
                Swal.fire('Oops...', submitData.message, 'error');
            }

        } catch(err) {
            console.error(err);
            Swal.fire('Error!', 'An unexpected error occurred. Try again.', 'error');
        } finally {
            btn.disabled = false;
        }
    });

});