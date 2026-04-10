document.addEventListener('DOMContentLoaded', async function() {
    const form = document.getElementById('addEmployeeForm');
    const btn = form.querySelector('button[type="submit"]');
    const employmentStatus = document.getElementById('employmentStatus');
    const dateRangeFields = document.getElementById('dateRangeFields');
    const rovingField = document.getElementById('rovingField');
    const rovingContainer = document.getElementById('rovingContainer');
    const remarks = form.querySelector('textarea[name="remarks"]');
    const remarksCount = document.getElementById('remarksCount');

    const mainBranchSelect = form.querySelector('select[name="branch"]');
    const mainBrandSelect = form.querySelector('select[name="brand"]');

    // =========================
    // Fetch branch-brand availability mapping
    // =========================
    let branchBrandPairs = [];
    try {
        const res = await fetch('functions/get_available_branches_brands.php');
        branchBrandPairs = await res.json(); // [{branch_name, brand_name, required_count, assigned_count}]
    } catch(err) {
        console.error('Failed to fetch branch-brand data', err);
    }

    // =========================
    // Convert all inputs to uppercase
    // =========================
    form.querySelectorAll('input[type="text"]').forEach(input => {
        input.addEventListener('input', () => input.value = input.value.toUpperCase());
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
        const value = employmentStatus.value;
        dateRangeFields.classList.add('d-none');
        rovingField.classList.add('d-none');

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
    // Add / Remove roving rows
    // =========================
    rovingContainer.addEventListener('click', function(e) {
        const row = e.target.closest('.roving-row');
        if (!row) return;

        if (e.target.classList.contains('add-branch')) {
            const clone = row.cloneNode(true);
            clone.querySelector('select').value = '';
            rovingContainer.appendChild(clone);
            populateRovingSelect(clone.querySelector('select'));
        }

        if (e.target.classList.contains('remove-branch')) {
            const rows = rovingContainer.querySelectorAll('.roving-row');
            if (rows.length > 1) row.remove();
            else row.querySelector('select').value = '';
        }
    });

    // =========================
    // Remarks character count
    // =========================
    remarks.addEventListener('input', function() {
        remarksCount.textContent = `${this.value.length} / 100`;
    });

    // =========================
    // Populate main branch select with availability
    // =========================
    function populateBranchSelect() {
        const uniqueBranches = [...new Set(branchBrandPairs.map(p => p.branch_name))];
        mainBranchSelect.innerHTML = '';
        uniqueBranches.forEach(b => {
            const opt = new Option(b, b);
            const allFull = branchBrandPairs
                .filter(p => p.branch_name === b)
                .every(p => p.assigned_count >= p.required_count);
            if (allFull) {
                opt.disabled = true;
                opt.text += ' (Full)';
            }
            mainBranchSelect.appendChild(opt);
        });
    }

    // =========================
    // Populate brand select based on branch
    // =========================
    function updateBrandSelect(selectBranch, selectBrand) {
        const branch = selectBranch.value;
        selectBrand.innerHTML = '';
        branchBrandPairs
            .filter(p => p.branch_name === branch)
            .forEach(p => {
                const opt = new Option(p.brand_name, p.brand_name);
                if (p.assigned_count >= p.required_count) {
                    opt.disabled = true;
                    opt.text += ' (Full)';
                }
                selectBrand.appendChild(opt);
            });
        if(selectBrand.selectedOptions[0]?.disabled) selectBrand.value = '';
    }

    // =========================
    // Populate roving selects
    // =========================
    function populateRovingSelect(select) {
        const uniqueBranches = [...new Set(branchBrandPairs.map(p => p.branch_name))];
        select.innerHTML = '';
        uniqueBranches.forEach(b => {
            const opt = new Option(b, b);
            const allFull = branchBrandPairs
                .filter(p => p.branch_name === b)
                .every(p => p.assigned_count >= p.required_count);
            if(allFull) {
                opt.disabled = true;
                opt.text += ' (Full)';
            }
            select.appendChild(opt);
        });
    }

    // Initial populate
    populateBranchSelect();
    updateBrandSelect(mainBranchSelect, mainBrandSelect);
    mainBranchSelect.addEventListener('change', () => updateBrandSelect(mainBranchSelect, mainBrandSelect));
    rovingContainer.querySelectorAll('.roving-select').forEach(s => populateRovingSelect(s));

    // =========================
    // Form submission
    // =========================
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const branch = mainBranchSelect.value;
        const brand = mainBrandSelect.value;
        const statusType = employmentStatus.value;

        // Start / End date validation
        const startDateInput = form.querySelector('input[name="start_date"]');
        const endDateInput = form.querySelector('input[name="end_date"]');

        // convert empty strings to null
        const startDate = startDateInput.value ? startDateInput.value : null;
        const endDate = endDateInput.value ? endDateInput.value : null;
        if (statusType === 'SEASONAL' || statusType === 'RELIEVER') {
            if (!startDate || !endDate) {
                return Swal.fire(
                    'Missing Dates',
                    'Start and End dates are required.',
                    'warning'
                );
            }

            if (new Date(startDate) > new Date(endDate)) {
                return Swal.fire(
                    'Invalid Dates',
                    'End date must be after start date.',
                    'error'
                );
            }
        }

        // Gather all branches (main + roving)
        let branchesToCheck = branch ? [branch] : [];
        if (statusType === 'ROVING') {
            const rovingBranches = Array.from(rovingContainer.querySelectorAll('.roving-select')).map(s => s.value);
            if(rovingBranches.includes('') || new Set(rovingBranches).size !== rovingBranches.length) {
                const msg = rovingBranches.includes('') ? 'Please select all roving branches.' : 'Duplicate branches are not allowed.';
                return Swal.fire('Roving Branch Error', msg, 'error');
            }
            rovingBranches.forEach(b => formData.append('roving_branches[]', b));
            branchesToCheck.push(...rovingBranches);
        }

        branchesToCheck = [...new Set(branchesToCheck)];

        // Client-side check: prevent saving full branch/brand combos
        for (let b of branchesToCheck) {
            const combo = branchBrandPairs.find(p => p.branch_name === b && p.brand_name === brand);
            if(!combo || combo.assigned_count >= combo.required_count) {
                return Swal.fire('Cannot Save', `Branch & Brand Invalid: ${b} & ${brand}. Choose another.`, 'error');
            }
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
        if(!confirm.isConfirmed) return;

        try {
            btn.disabled = true;

            const status = (statusType === 'ROVING' || (branch && brand)) ? 'ACTIVE' : 'INACTIVE';
            formData.set('status', status);
            formData.set('employment_status', statusType);
            formData.set('assigned_by', window.currentUser || 'SYSTEM');

            const res = await fetch('functions/add_employee.php', { method: 'POST', body: formData });
            const data = await res.json();

            if(data.status === 'success') {
                Swal.fire('Employee Added!', data.message, 'success').then(() => {
                    form.reset();
                    dateRangeFields.classList.add('d-none');
                    rovingField.classList.add('d-none');
                    remarksCount.textContent = '0 / 100';
                    rovingContainer.querySelectorAll('.roving-row').forEach((row, idx) => { if(idx>0) row.remove(); });
                    rovingContainer.querySelector('select').value = '';
                    bootstrap.Modal.getInstance(document.getElementById('addEmployeeModal')).hide();
                    location.reload();
                });
            } else {
                Swal.fire('Oops...', data.message, 'error');
            }

        } catch(err) {
            console.error(err);
            Swal.fire('Error!', 'An unexpected error occurred. Try again.', 'error');
        } finally {
            btn.disabled = false;
        }
    });

});