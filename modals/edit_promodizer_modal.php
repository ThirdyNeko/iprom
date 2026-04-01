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

                <!-- 🔴 Terminated Notice -->
                <div id="terminatedNotice" class="alert alert-danger d-none">
                    This employee is terminated and can no longer be modified.
                </div>

                <!-- Hidden ID -->
                <input type="hidden" id="editPromodizerId">

                <!-- Employee Info Table -->
                <table class="table table-bordered table-striped table-sm mb-3">
                    <tbody>
                        <tr>
                            <th>First Name</th>
                            <td><input type="text" id="editFirstName" class="form-control"></td>
                            <th>Last Name</th>
                            <td><input type="text" id="editLastName" class="form-control"></td>
                        </tr>

                        <script>
                        document.getElementById('editFirstName').addEventListener('input', function() {
                            this.value = this.value.toUpperCase();
                        });
                        document.getElementById('editLastName').addEventListener('input', function() {
                            this.value = this.value.toUpperCase();
                        });
                        </script>
                        <tr>
                            <th>Branch</th>
                            <td>
                                <select id="editBranch" class="form-select"></select>
                            </td>

                            <th>Brand</th>
                            <td>
                                <select id="editBrand" class="form-select"></select>
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td id="editStatus"></td>
                            <th>Last Assigned By</th>
                            <td id="editLastAssignedBy"></td>
                        </tr>
                        <tr>
                            <th>Assignment Date</th>
                            <td id="editAssignmentDate"></td>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <th>Date Hired</th>
                            <td id="editDateHired"></td>
                            <th>Date of Return</th>
                            <td id="editDateReturn"></td>
                        </tr>
                        <tr>
                            <th>Date Separated</th>
                            <td id="editDateSeparated"></td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-danger" id="terminateBtn">Terminate</button>
                    <button type="button" class="btn btn-warning" id="unassignBtn">Unassign</button>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" id="saveBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script>
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
            // Fetch available branches & brands dynamically
            const availRes = await fetch('functions/get_available_branches_brands.php');
            const availData = await availRes.json();
            const branches = availData.branches || [];
            const brands   = availData.brands || [];

            // Get selects
            const branchSelect = document.getElementById('editBranch');
            const brandSelect  = document.getElementById('editBrand');

            // Clear previous options
            branchSelect.innerHTML = '';
            brandSelect.innerHTML  = '';

            // Populate dropdowns
            branches.forEach(b => branchSelect.appendChild(new Option(b, b)));
            brands.forEach(b => brandSelect.appendChild(new Option(b, b)));

            // Ensure employee's current selection exists
            if (p.branch && !branches.includes(p.branch)) branchSelect.appendChild(new Option(p.branch, p.branch));
            if (p.brand && !brands.includes(p.brand)) brandSelect.appendChild(new Option(p.brand, p.brand));

            // Set selected values
            branchSelect.value = p.branch || '';
            brandSelect.value  = p.brand || '';

            // 🔒 HANDLE TERMINATED STATE
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

    const formData = new FormData();
    formData.set('id', id);
    formData.set('first_name', firstName);
    formData.set('last_name', lastName);
    formData.set('branch', branch || null);
    formData.set('brand', brand || null);

    try {
        if(branch && brand){
            const res = await fetch('functions/check_assignment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ branch, brand })
            });
            const data = await res.json();

            if(!data.exists){
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Assignment',
                    text: 'No assignment exists for the selected Branch & Brand.'
                });
                return;
            }
        }

        const status = (branch && brand) ? 'ACTIVE' : 'INACTIVE';
        formData.set('status', status);

        await sendAction(formData, 'Save Changes');

    } catch(err) {
        console.error(err);
        Swal.fire({ icon: 'error', title: 'Error', text: 'An error occurred.' });
    }
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
</script>