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
                        <input type="text" name="first_name" id="firstName" class="form-control" required style="text-transform: uppercase;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="lastName" class="form-control" required style="text-transform: uppercase;">
                    </div>

                    <script>
                    document.getElementById('firstName').addEventListener('input', function() {
                        this.value = this.value.toUpperCase();
                    });
                    document.getElementById('lastName').addEventListener('input', function() {
                        this.value = this.value.toUpperCase();
                    });
                    </script>

                    <?php
                    $pdo = qa_db();

                    // Fetch distinct branches
                    $branches = $pdo->query("
                        SELECT DISTINCT branch_name 
                        FROM assignment
                        WHERE assigned_count < required_count
                        ORDER BY branch_name
                    ")->fetchAll(PDO::FETCH_COLUMN);

                    $brands = $pdo->query("
                        SELECT DISTINCT brand_name 
                        FROM assignment
                        WHERE assigned_count < required_count
                        ORDER BY brand_name
                    ")->fetchAll(PDO::FETCH_COLUMN);
                    ?>

                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select name="branch" class="form-select">
                            <option value="" selected disabled>Unassigned</option>
                            <?php foreach($branches as $branch): ?>
                                <option value="<?= htmlspecialchars($branch) ?>"><?= htmlspecialchars($branch) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Brand</label>
                        <select name="brand" class="form-select">
                            <option value="" selected disabled>Unassigned</option>
                            <?php foreach($brands as $brand): ?>
                                <option value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="status" id="employeeStatus" value="">

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- Use CDN for reliability -->
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script>
document.getElementById('addEmployeeForm').addEventListener('submit', async function(e){
    e.preventDefault();

    const form = this;
    const btn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    const branch = form.querySelector('select[name="branch"]').value.trim();
    const brand  = form.querySelector('select[name="brand"]').value.trim();

    // 🛑 CONFIRMATION FIRST
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

    // ❌ If user cancels → stop here
    if (!confirm.isConfirmed) return;

    try {
        btn.disabled = true;

        // 1️⃣ Validate that branch + brand exist
        const res = await fetch('functions/check_assignment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ branch, brand })
        });
        const data = await res.json();

        if (!data.exists) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Selection',
                text: 'No assignment exists for the selected Branch & Brand.',
                confirmButtonText: 'OK'
            });
            return;
        }

        // 2️⃣ Set status
        const status = (branch && brand) ? 'ACTIVE' : 'INACTIVE';
        formData.set('status', status);
        formData.set('assigned_by', '<?= $_SESSION["username"] ?>');

        // 3️⃣ Submit employee
        const submitRes = await fetch('functions/add_employee.php', {
            method: 'POST',
            body: formData
        });

        const submitData = await submitRes.json();

        if(submitData.status === 'success'){
            Swal.fire({
                icon: 'success',
                title: 'Employee Added!',
                text: submitData.message,
                confirmButtonText: 'OK'
            }).then(() => {
                form.reset();
                const modal = bootstrap.Modal.getInstance(document.getElementById('addEmployeeModal'));
                modal.hide();
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: submitData.message,
                confirmButtonText: 'OK'
            });
        }

    } catch(err) {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An unexpected error occurred. Try again.',
            confirmButtonText: 'OK'
        });
    } finally {
        btn.disabled = false;
    }
});
</script>