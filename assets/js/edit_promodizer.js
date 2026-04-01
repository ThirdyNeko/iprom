const modalEl = document.getElementById('editPromodizerModal');

// Populate modal
document.querySelectorAll('.clickable-row').forEach(row => {
    row.addEventListener('click', async () => {
        const id = row.dataset.id;
        const modal = new bootstrap.Modal(modalEl);

        try {
            // 1️⃣ Fetch employee data
            const res = await fetch(`functions/get_employee.php?id=${id}`);
            const p = await res.json();
            if (!p || !p.id) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Employee not found' });
                return;
            }

            // Fill basic fields
            document.getElementById('editPromodizerId').value = p.id;
            document.getElementById('editFirstName').value = p.first_name;
            document.getElementById('editLastName').value = p.last_name;
            document.getElementById('editStatus').textContent = p.status || '-';
            document.getElementById('editLastAssignedBy').textContent = p.last_assigned_by || '-';
            document.getElementById('editAssignmentDate').textContent = p.assignment_date ? new Date(p.assignment_date).toLocaleDateString() : '-';
            document.getElementById('editDateHired').textContent = p.created_at ? new Date(p.created_at).toLocaleDateString() : '-';
            document.getElementById('editDateReturn').textContent = p.date_of_return ? new Date(p.date_of_return).toLocaleDateString() : '-';
            document.getElementById('editDateSeparated').textContent = p.date_separated ? new Date(p.date_separated).toLocaleDateString() : '-';

            // 2️⃣ Fetch assignments data
            const availRes = await fetch('functions/get_available_branches_brands.php');
            const combos = await availRes.json(); // [{branch_name, brand_name, required_count, assigned_count}]

            const branchSelect = document.getElementById('editBranch');
            const brandSelect  = document.getElementById('editBrand');

            // Determine full combos and full branches
            const fullCombos = combos
                .filter(c => c.assigned_count >= c.required_count)
                .map(c => `${c.branch_name}||${c.brand_name}`);

            const fullBranches = combos
                .filter(c => c.assigned_count >= c.required_count)
                .map(c => c.branch_name);

            const uniqueBranches = [...new Set(combos.map(c => c.branch_name))];

            // Populate branch select
            branchSelect.innerHTML = '';
            uniqueBranches.forEach(b => {
                const opt = new Option(b, b);
                const allBrandsFull = combos
                    .filter(c => c.branch_name === b)
                    .every(c => c.assigned_count >= c.required_count);
                if (allBrandsFull) {
                    opt.disabled = true;
                    opt.text += ' (Full)';
                }
                branchSelect.appendChild(opt);
            });

            // Function to update brand options based on selected branch
            function updateBrands() {
                const branch = branchSelect.value;
                const brandsForBranch = combos
                    .filter(c => c.branch_name === branch)
                    .map(c => c.brand_name);

                brandSelect.innerHTML = '';
                brandsForBranch.forEach(b => {
                    const opt = new Option(b, b);
                    if(fullCombos.includes(`${branch}||${b}`)) {
                        opt.disabled = true;
                        opt.text += " (Full)";
                    }
                    brandSelect.appendChild(opt);
                });

                // If current value is disabled, reset
                if(brandSelect.selectedOptions[0]?.disabled) brandSelect.value = '';
            }

            branchSelect.addEventListener('change', updateBrands);

            // Initial populate
            branchSelect.value = p.branch || '';
            updateBrands();
            brandSelect.value = p.brand || '';

            // 🔒 Handle terminated state
            const status = (p.status || '').toLowerCase();
            const inputs = modalEl.querySelectorAll('input, select');
            const saveBtn = document.getElementById('saveBtn');
            const unassignBtn = document.getElementById('unassignBtn');
            const terminateBtn = document.getElementById('terminateBtn');
            const notice = document.getElementById('terminatedNotice');

            if (status === 'terminated') {
                inputs.forEach(el => el.disabled = true);
                saveBtn.style.display = 'none';
                unassignBtn.style.display = 'none';
                terminateBtn.style.display = 'none';
                notice.classList.remove('d-none');
            } else {
                inputs.forEach(el => el.disabled = false);
                saveBtn.style.display = 'inline-block';
                unassignBtn.style.display = 'inline-block';
                terminateBtn.style.display = 'inline-block';
                notice.classList.add('d-none');
            }

            modal.show();

        } catch(err) {
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load employee data' });
        }
    });
});

// Helper AJAX
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

// Save
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

    const id = document.getElementById('editPromodizerId').value;
    const firstName = document.getElementById('editFirstName').value.trim();
    const lastName  = document.getElementById('editLastName').value.trim();
    const branch    = document.getElementById('editBranch').value;
    const brand     = document.getElementById('editBrand').value;

    // Prevent saving full branch/brand
    const availRes = await fetch('functions/get_available_branches_brands.php');
    const combos = await availRes.json();
    const fullCombos = combos
        .filter(c => c.assigned_count >= c.required_count)
        .map(c => `${c.branch_name}||${c.brand_name}`);

    const branchFull = combos
        .filter(c => c.branch_name === branch)
        .every(c => c.assigned_count >= c.required_count);

    if(branchFull || fullCombos.includes(`${branch}||${brand}`)) {
        Swal.fire({
            icon: 'error',
            title: 'Cannot Save',
            text: 'Selected branch or brand is full. Please choose another.'
        });
        return;
    }

    const formData = new FormData();
    formData.set('id', id);
    formData.set('first_name', firstName);
    formData.set('last_name', lastName);
    formData.set('branch', branch || null);
    formData.set('brand', brand || null);
    formData.set('status', (branch && brand) ? 'ACTIVE' : 'INACTIVE');

    sendAction(formData, 'Save Changes');
});

// Unassign
document.getElementById('unassignBtn').addEventListener('click', async () => {

    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Unassign Employee?',
        text: 'This will remove the employee from their current branch and brand.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Unassign',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#f0ad4e',
        reverseButtons: true
    });

    if (!confirm.isConfirmed) return;

    const formData = new FormData();
    formData.set('id', document.getElementById('editPromodizerId').value);
    formData.set('first_name', document.getElementById('editFirstName').value.trim());
    formData.set('last_name', document.getElementById('editLastName').value.trim());
    formData.set('branch', 'UNASSIGNED');
    formData.set('brand', 'UNASSIGNED');
    formData.set('status', 'INACTIVE');

    sendAction(formData, 'Unassign');
});

// Terminate
document.getElementById('terminateBtn').addEventListener('click', async () => {

    const confirm = await Swal.fire({
        icon: 'error',
        title: 'Terminate Employee?',
        html: `
            <b>This action is irreversible.</b><br>
            The employee will be permanently marked as terminated<br>
            and will no longer be editable.
        `,
        showCancelButton: true,
        confirmButtonText: 'Yes, Terminate',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
        reverseButtons: true
    });

    if (!confirm.isConfirmed) return;

    const formData = new FormData();
    formData.set('id', document.getElementById('editPromodizerId').value);
    formData.set('first_name', document.getElementById('editFirstName').value.trim());
    formData.set('last_name', document.getElementById('editLastName').value.trim());
    formData.set('branch', 'UNASSIGNED');
    formData.set('brand', 'UNASSIGNED');
    formData.set('status', 'TERMINATED');

    sendAction(formData, 'Terminate');
});
