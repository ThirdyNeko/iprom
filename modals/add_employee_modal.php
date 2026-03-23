<div class="modal fade" id="addEmployeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <form id="addEmployeeForm">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div id="employeeAlert"></div> <!-- Alert container -->

                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <input type="text" name="branch" class="form-control" placeholder="Unassigned">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Brand</label>
                        <input type="text" name="brand" class="form-control" placeholder="Unassigned">
                    </div>

                    <input type="hidden" name="status" id="employeeStatus" value="">

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
document.getElementById('addEmployeeForm').addEventListener('submit', function(e){
    e.preventDefault();

    const form = this;
    const btn = form.querySelector('button[type="submit"]');
    const alertDiv = document.getElementById('employeeAlert');
    const formData = new FormData(form);

    // 👇 Determine status
    const branch = form.querySelector('input[name="branch"]').value.trim();
    const brand  = form.querySelector('input[name="brand"]').value.trim();
    const status = (branch && brand) ? 'Active' : 'Inactive';
    formData.set('status', status); // dynamically set status

    btn.disabled = true;

    fetch('functions/add_employee.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alertDiv.innerHTML = `<div class="alert alert-${data.status}">${data.message}</div>`;

        if(data.status === 'success'){
            form.reset();

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addEmployeeModal'));
            modal.hide();

            // Reload table or page
            setTimeout(() => location.reload(), 500);
        }

        setTimeout(() => alertDiv.innerHTML = '', 3000);
    })
    .catch(err => console.error(err))
    .finally(() => btn.disabled = false);
});
</script>