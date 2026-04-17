document.addEventListener('DOMContentLoaded', async function() {
    const form = document.getElementById('addEmployeeForm');
    const btn = form.querySelector('button[type="submit"]');
    const employmentStatus = document.getElementById('employmentStatus');
    const dateRangeFields = document.getElementById('dateRangeFields');
    const rovingField = document.getElementById('rovingField');
    const rovingContainer = document.getElementById('rovingContainer');
    const remarks = form.querySelector('textarea[name="remarks"]');
    const remarksCount = document.getElementById('remarksCount');
    const subStatus = document.getElementById('subStatus');

    const mainBranchSelect = form.querySelector('select[name="branch"]');
    const mainBrandSelect = form.querySelector('select[name="brand"]');

    const multiBrandField = document.getElementById('multiBrandField');
    const multiBrandContainer = document.getElementById('multiBrandContainer');

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
    subStatus.addEventListener('change', toggleFields);

    function toggleFields() {
        const empStatus = employmentStatus.value;
        const sub = subStatus.value;
        multiBrandField.classList.add('d-none');
        multiBrandField.querySelectorAll('.multi-brand-select').forEach(s => s.required = false);

        // Reset
        dateRangeFields.classList.add('d-none');
        rovingField.classList.add('d-none');

        dateRangeFields.querySelectorAll('input').forEach(i => i.required = false);
        rovingField.querySelectorAll('.roving-select').forEach(s => s.required = false);

        // Show date range for seasonal/reliever
        if (empStatus === 'SEASONAL' || empStatus === 'RELIEVER') {
            dateRangeFields.classList.remove('d-none');
            dateRangeFields.querySelectorAll('input').forEach(i => i.required = true);
        }

        // ✅ NEW: Show roving when MULTI BRANCH
        if (sub === 'MULTI BRANCH') {
            rovingField.classList.remove('d-none');
            rovingField.querySelectorAll('.roving-select').forEach(s => s.required = true);
        }
        if (sub === 'MULTI BRAND') {
            multiBrandField.classList.remove('d-none');
            multiBrandField.querySelectorAll('.multi-brand-select').forEach(s => s.required = true);
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
            requestAnimationFrame(() => {
                populateMultiBrandSelect(
                    clone.querySelector('select'),
                    mainBranchSelect.value,
                    mainBrandSelect.value
                );
            });
            rovingContainer.appendChild(clone);
            populateRovingSelect(clone.querySelector('select'));
        }

        if (e.target.classList.contains('remove-branch')) {
            const rows = rovingContainer.querySelectorAll('.roving-row');
            if (rows.length > 1) row.remove();
            else row.querySelector('select').value = '';
        }
    });

    multiBrandContainer.addEventListener('click', function(e) {
        const row = e.target.closest('.multi-brand-row');
        if (!row) return;

        if (e.target.classList.contains('add-brand')) {
            const clone = row.cloneNode(true);
            const select = clone.querySelector('select');

            select.value = '';
            multiBrandContainer.appendChild(clone);

            // wait until DOM is fully attached
            requestAnimationFrame(() => {
                populateMultiBrandSelect(
                    select,
                    mainBranchSelect.value,
                    mainBrandSelect.value
                );
            });
        }

        if (e.target.classList.contains('remove-brand')) {
            const rows = multiBrandContainer.querySelectorAll('.multi-brand-row');
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
        mainBranchSelect.innerHTML = '<option value="" disabled selected>-- Select Branch --</option>';
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
        selectBrand.innerHTML = '<option value="" disabled selected>-- Select Brand --</option>';
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
    }

    // =========================
    // Populate roving selects
    // =========================
    function populateRovingSelect(select) {
        const currentBranch = mainBranchSelect.value;

        const uniqueBranches = [
            ...new Set(branchBrandPairs.map(p => p.branch_name))
        ];

        select.innerHTML = '<option value="" disabled selected>-- Select Branch --</option>';

        uniqueBranches.forEach(b => {
            if (b === currentBranch) return; // ❌ exclude selected branch

            const opt = new Option(b, b);

            const allFull = branchBrandPairs
                .filter(p => p.branch_name === b)
                .every(p => p.assigned_count >= p.required_count);

            if (allFull) {
                opt.disabled = true;
                opt.text += ' (Full)';
            }

            select.appendChild(opt);
        });
    }

    function populateMultiBrandSelect(select, selectedBranch, excludedBrand) {

        const branch = selectedBranch || mainBranchSelect.value;
        const brandToExclude = excludedBrand || mainBrandSelect.value;

        select.innerHTML = '<option value="" disabled selected>-- Select Brand --</option>';

        if (!branch) return;

        const filtered = branchBrandPairs.filter(
            p => p.branch_name === branch
        );

        filtered.forEach(p => {

            // ✅ IMPORTANT FIX: use passed value, not global only
            if (p.brand_name === brandToExclude) return;

            const opt = new Option(p.brand_name, p.brand_name);

            if (p.assigned_count >= p.required_count) {
                opt.disabled = true;
                opt.text += ' (Full)';
            }

            select.appendChild(opt);
        });
    }

    mainBranchSelect.addEventListener('change', () => {
        updateBrandSelect(mainBranchSelect, mainBrandSelect);

        const branch = mainBranchSelect.value;
        const brand = mainBrandSelect.value;

        requestAnimationFrame(() => {
            document.querySelectorAll('.multi-brand-select').forEach(sel => {
                populateMultiBrandSelect(sel, branch, brand);
            });
        });
    });

    mainBrandSelect.addEventListener('change', () => {

        const branch = mainBranchSelect.value;
        const brand = mainBrandSelect.value;

        requestAnimationFrame(() => {
            document.querySelectorAll('.multi-brand-select').forEach(sel => {
                populateMultiBrandSelect(sel, branch, brand);
            });
        });
    });

    // Initial populate
    populateBranchSelect();
    updateBrandSelect(mainBranchSelect, mainBrandSelect);
    rovingContainer.querySelectorAll('.roving-select').forEach(s => populateRovingSelect(s));
    multiBrandContainer
        .querySelectorAll('.multi-brand-select')
        .forEach(sel => {
            populateMultiBrandSelect(sel, mainBranchSelect.value, mainBrandSelect.value);
        });

    // =========================
    // Form submission
    // =========================

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        // Start / End date validation
        const startDateInput = form.querySelector('input[name="start_date"]');
        const endDateInput = form.querySelector('input[name="end_date"]');
        // ✅ FIX: remove empty date fields so PHP gets NULL
        if (!startDateInput.value) formData.delete('start_date');
        if (!endDateInput.value) formData.delete('end_date');
        const branch = mainBranchSelect.value;
        const brand = mainBrandSelect.value;
        const statusType = employmentStatus.value;
        const sub = subStatus.value;
        const dateHiredInput = form.querySelector('input[name="date_hired"]');

        // Set max = today
        const today = new Date().toISOString().split('T')[0];
        dateHiredInput.setAttribute('max', today);

        

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

        const dateHiredValue = dateHiredInput.value;

        if (dateHiredValue) {
            const today = new Date().toISOString().split('T')[0];

            if (dateHiredValue > today) {
                return Swal.fire(
                    'Invalid Date Hired',
                    'Date hired cannot be in the future.',
                    'error'
                );
            }
        }

        // Gather all branches (main + roving)
        let branchesToCheck = branch ? [branch] : [];
        if (sub === 'MULTI BRANCH') {
            const rovingBranches = Array.from(rovingContainer.querySelectorAll('.roving-select')).map(s => s.value);
            if(rovingBranches.includes('') || new Set(rovingBranches).size !== rovingBranches.length) {
                const msg = rovingBranches.includes('') ? 'Please select all roving branches.' : 'Duplicate branches are not allowed.';
                return Swal.fire('Roving Branch Error', msg, 'error');
            }
            rovingBranches.forEach(b => formData.append('roving_branches[]', b));
            branchesToCheck.push(...rovingBranches);
        }

        let multiBrands = [];

        if (sub === 'MULTI BRAND') {
            multiBrands = Array.from(
                multiBrandContainer.querySelectorAll('.multi-brand-select')
            ).map(s => s.value);

            if (multiBrands.includes('') || new Set(multiBrands).size !== multiBrands.length) {
                const msg = multiBrands.includes('')
                    ? 'Please select all brands.'
                    : 'Duplicate brands are not allowed.';
                return Swal.fire('Multi Brand Error', msg, 'error');
            }

            multiBrands.forEach(b => formData.append('multi_brands[]', b));
        }

        branchesToCheck = [...new Set(branchesToCheck)];

        if (sub === 'MULTI BRAND') {
            for (let b of multiBrands) {
                const combo = branchBrandPairs.find(p => p.branch_name === branch && p.brand_name === b);
                if (!combo || combo.assigned_count >= combo.required_count) {
                    return Swal.fire('Cannot Save', `Invalid: ${branch} & ${b}`, 'error');
                }
            }
        }

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
            confirmButtonColor: '#d33'
        });
        if(!confirm.isConfirmed) return;

        try {
            btn.disabled = true;
            
            let status = (
                sub === 'MULTI BRANCH' ||
                sub === 'MULTI BRAND' ||
                (branch && brand)
            ) ? 'ACTIVE' : 'INACTIVE';

            // =========================
            // SEASONAL / RELIEVER RULE
            // =========================
            if ((statusType === 'SEASONAL' || statusType === 'RELIEVER') && startDateInput.value) {
                const today = new Date().toISOString().split('T')[0];

                if (today < startDateInput.value) {
                    status = 'INACTIVE';
                }
            }

            formData.set('status', status);
            formData.set('employment_status', statusType);
            formData.set('assigned_by', window.currentUser || 'SYSTEM');
            formData.set('updated_by', window.currentUser || 'SYSTEM');

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