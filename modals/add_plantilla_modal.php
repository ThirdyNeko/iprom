<div class="modal fade" id="addPlantillaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <!-- ✅ REMOVE action, ADD id -->
            <form id="addPlantillaForm">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Plantilla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <!-- ✅ Alert container -->
                    <div id="plantillaAlert"></div>

                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <input type="text" name="branch_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Brand</label>
                        <input type="text" name="brand_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Required Count</label>
                        <input type="number" name="required_count" class="form-control" required>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Plantilla</button>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
document.getElementById('addPlantillaForm').addEventListener('submit', function(e){
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);
    const btn = form.querySelector('button[type="submit"]');
    const alertDiv = document.getElementById('plantillaAlert');

    btn.disabled = true;

    fetch('functions/add_plantilla.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alertDiv.innerHTML = `
            <div class="alert alert-${data.status}">
                ${data.message}
            </div>
        `;

        if(data.status === 'success'){
            form.reset();

            // 🔥 Close modal after success
            const modal = bootstrap.Modal.getInstance(document.getElementById('addPlantillaModal'));
            modal.hide();

            // 🔥 Reload table/page
            setTimeout(() => location.reload(), 500);
        }

        setTimeout(() => alertDiv.innerHTML = '', 3000);
    })
    .catch(err => console.error(err))
    .finally(() => btn.disabled = false);
});
</script>