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

                    <?php
                    $pdo = qa_db();

                    // Fetch distinct branches
                    $branches = $pdo->query("SELECT DISTINCT branch_name FROM assignment ORDER BY branch_name")
                                    ->fetchAll(PDO::FETCH_COLUMN);

                    // Fetch distinct brands
                    $brands = $pdo->query("SELECT DISTINCT brand_name FROM assignment ORDER BY brand_name")
                                ->fetchAll(PDO::FETCH_COLUMN);
                    ?>

                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select name="branch" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach($branches as $branch): ?>
                                <option value="<?= htmlspecialchars($branch) ?>"><?= htmlspecialchars($branch) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Brand</label>
                        <select name="brand" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach($brands as $brand): ?>
                                <option value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
                            <?php endforeach; ?>
                        </select>
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
document.getElementById('addEmployeeForm').addEventListener('submit', async function(e){
    e.preventDefault();

    const form = this;
    const btn = form.querySelector('button[type="submit"]');
    const alertDiv = document.getElementById('employeeAlert');
    const formData = new FormData(form);

    const branch = form.querySelector('select[name="branch"]').value.trim();
    const brand  = form.querySelector('select[name="brand"]').value.trim();

    // Validate that branch + brand exist in assignment table
    try {
        btn.disabled = true;

        const res = await fetch('functions/check_assignment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ branch, brand })
        });
        const data = await res.json();

        if (!data.exists) {
            alertDiv.innerHTML = `<div class="alert alert-danger">No assignment exists for the selected Branch & Brand.</div>`;
            setTimeout(() => alertDiv.innerHTML = '', 3000);
            btn.disabled = false;
            return; // stop form submission
        }

        // Determine status dynamically
        const status = (branch && brand) ? 'Active' : 'Inactive';
        formData.set('status', status);

        // Submit employee
        const submitRes = await fetch('functions/add_employee.php', {
            method: 'POST',
            body: formData
        });
        const submitData = await submitRes.json();

        alertDiv.innerHTML = `<div class="alert alert-${submitData.status}">${submitData.message}</div>`;

        if(submitData.status === 'success'){
            form.reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('addEmployeeModal'));
            modal.hide();
            setTimeout(() => location.reload(), 500);
        }

        setTimeout(() => alertDiv.innerHTML = '', 3000);

    } catch(err) {
        console.error(err);
        alertDiv.innerHTML = `<div class="alert alert-danger">An error occurred. Try again.</div>`;
        setTimeout(() => alertDiv.innerHTML = '', 3000);
    } finally {
        btn.disabled = false;
    }
});
</script>