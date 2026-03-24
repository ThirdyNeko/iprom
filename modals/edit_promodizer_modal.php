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
                        <tr>
                            <th>Branch</th>
                            <td>
                                <select id="editBranch" class="form-select">
                                    <option value="">Unassigned</option>
                                    <?php foreach($branches as $branch): ?>
                                        <option value="<?= htmlspecialchars($branch) ?>"><?= htmlspecialchars($branch) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <th>Brand</th>
                            <td>
                                <select id="editBrand" class="form-select">
                                    <option value="">Unassigned</option>
                                    <?php foreach($brands as $brand): ?>
                                        <option value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const modalEl = document.getElementById('editPromodizerModal');
const alertDiv = document.getElementById('editAlert');

// Populate modal when clicking a row
document.querySelectorAll('.clickable-row').forEach(row => {
    row.addEventListener('click', async () => {
        const id = row.dataset.id;
        const modal = new bootstrap.Modal(modalEl);

        // Fetch employee data
        const res = await fetch(`functions/get_employee.php?id=${id}`);
        const p = await res.json();
        if (!p || !p.id) return alert('Employee not found');

        // Fill table fields
        document.getElementById('editPromodizerId').value = p.id;
        document.getElementById('editFirstName').value = p.first_name;
        document.getElementById('editLastName').value = p.last_name;
        document.getElementById('editBranch').value = p.branch || '';
        document.getElementById('editBrand').value = p.brand || '';
        document.getElementById('editStatus').textContent = p.status || '-';
        document.getElementById('editLastAssignedBy').textContent = p.last_assigned_by || '-';
        document.getElementById('editAssignmentDate').textContent = p.assignment_date ? new Date(p.assignment_date).toLocaleDateString() : '-';
        document.getElementById('editDateHired').textContent = p.created_at ? new Date(p.created_at).toLocaleDateString() : '-';
        document.getElementById('editDateReturn').textContent = p.date_of_return ? new Date(p.date_of_return).toLocaleDateString() : '-';
        document.getElementById('editDateSeparated').textContent = p.date_separated ? new Date(p.date_separated).toLocaleDateString() : '-';

        modal.show();
    });
});

// Helper function to send AJAX POST
async function sendAction(data) {
    const btns = modalEl.querySelectorAll('button');
    btns.forEach(b => b.disabled = true);

    try {
        const res = await fetch('functions/update_promodizer.php', { method: 'POST', body: data });
        const result = await res.json();
        alertDiv.innerHTML = `<div class="alert alert-${result.status}">${result.message}</div>`;
        if(result.status === 'success') {
            bootstrap.Modal.getInstance(modalEl).hide();
            setTimeout(() => location.reload(), 500);
        }
        setTimeout(() => alertDiv.innerHTML = '', 3000);
    } catch(err) {
        console.error(err);
        alertDiv.innerHTML = `<div class="alert alert-danger">An error occurred</div>`;
        setTimeout(() => alertDiv.innerHTML = '', 3000);
    } finally {
        btns.forEach(b => b.disabled = false);
    }
}

// Save changes
document.getElementById('saveBtn').addEventListener('click', () => {
    const formData = new FormData();
    formData.set('id', document.getElementById('editPromodizerId').value);
    formData.set('first_name', document.getElementById('editFirstName').value.trim());
    formData.set('last_name', document.getElementById('editLastName').value.trim());
    formData.set('branch', document.getElementById('editBranch').value || null);
    formData.set('brand', document.getElementById('editBrand').value || null);
    formData.set('status', document.getElementById('editBranch').value && document.getElementById('editBrand').value ? 'Active' : 'Inactive');

    sendAction(formData);
});

// Unassign
document.getElementById('unassignBtn').addEventListener('click', () => {
    const formData = new FormData();
    formData.set('id', document.getElementById('editPromodizerId').value);
    formData.set('first_name', document.getElementById('editFirstName').value.trim());
    formData.set('last_name', document.getElementById('editLastName').value.trim());
    formData.set('branch', 'Unassigned');
    formData.set('brand', 'Unassigned');
    formData.set('status', 'Inactive');
    sendAction(formData);
});

// Terminate
document.getElementById('terminateBtn').addEventListener('click', () => {
    const formData = new FormData();
    formData.set('id', document.getElementById('editPromodizerId').value);
    formData.set('first_name', document.getElementById('editFirstName').value.trim());
    formData.set('last_name', document.getElementById('editLastName').value.trim());
    formData.set('branch', 'Unassigned');
    formData.set('brand', 'Unassigned');
    formData.set('status', 'Terminated');
    sendAction(formData);
});
</script>