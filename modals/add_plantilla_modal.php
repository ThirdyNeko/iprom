<?php
// Call the stored procedure
$stmt = $pdo->query("EXEC get_branches_brands");

$plantillaBranches = [];
$plantillaBrands = [];

// Fetch first result set: branches
if ($stmt) {
    $plantillaBranches = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Move to next result set: brands
    if ($stmt->nextRowset()) {
        $plantillaBrands = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>

<div class="modal fade" id="addPlantillaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addPlantillaForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Plantilla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                   <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select name="branch" class="form-select" required>
                            <option value="">Select Branch</option>
                            <?php foreach($plantillaBranches as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Brand</label>
                        <select name="brand" class="form-select" required>
                            <option value="">Select Brand</option>
                            <?php foreach($plantillaBrands as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Plantilla Count</label>
                        <input type="number" name="required_count" class="form-control" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Add Plantilla</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.getElementById('addPlantillaForm').addEventListener('submit', async function(e){
    e.preventDefault();

    const form = this;
    const btn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    const branch = form.querySelector('select[name="branch"]').value.trim();
    const brand  = form.querySelector('select[name="brand"]').value.trim();

    if (!branch || !brand) {
        Swal.fire({
            icon: 'warning',
            title: 'Required Fields',
            text: 'Please select both Branch and Brand.',
            confirmButtonText: 'OK'
        });
        return;
    }

    try {
        btn.disabled = true;

        // 1️⃣ Check if plantilla already exists
        const checkRes = await fetch('functions/check_plantilla.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ branch, brand })
        });
        const checkData = await checkRes.json();

        if(checkData.exists){
            Swal.fire({
                icon: 'error',
                title: 'Duplicate Plantilla',
                text: `A plantilla already exists for Branch "${branch}" and Brand "${brand}".`,
                confirmButtonText: 'OK'
            });
            return;
        }

        // 2️⃣ Confirm action
        const confirm = await Swal.fire({
            icon: 'question',
            title: 'Add Plantilla?',
            text: `Branch: ${branch}\nBrand: ${brand}`,
            showCancelButton: true,
            confirmButtonText: 'Yes, Add',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#28a745',
            reverseButtons: true
        });

        if (!confirm.isConfirmed) return;

        // 3️⃣ Submit plantilla
        const submitRes = await fetch('functions/add_plantilla.php', {
            method: 'POST',
            body: formData
        });
        const submitData = await submitRes.json();

        if(submitData.success){
            Swal.fire({
                icon: 'success',
                title: 'Plantilla Added!',
                text: `Branch "${branch}" and Brand "${brand}" has been added successfully.`,
                confirmButtonText: 'OK'
            }).then(() => {
                form.reset();
                const modal = bootstrap.Modal.getInstance(document.getElementById('addPlantillaModal'));
                modal.hide();
                window.assignmentTable.ajax.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: submitData.message || 'Failed to add plantilla.',
                confirmButtonText: 'OK'
            });
        }

    } catch(err){
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Unexpected Error',
            text: 'An unexpected error occurred. Please try again.',
            confirmButtonText: 'OK'
        });
    } finally {
        btn.disabled = false;
    }
});
</script>