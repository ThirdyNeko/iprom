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

<!-- Include SweetAlert2 CDN if not already included -->
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script>
document.getElementById('addPlantillaForm').addEventListener('submit', async function(e){
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);
    const btn = form.querySelector('button[type="submit"]');

    try {
        btn.disabled = true;

        const res = await fetch('functions/add_plantilla.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();

        if(data.status === 'success'){
            await Swal.fire({
                icon: 'success',
                title: 'Plantilla Added!',
                text: data.message,
                confirmButtonText: 'OK'
            });

            form.reset();

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addPlantillaModal'));
            modal.hide();

            // Reload table/page
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: data.message,
                confirmButtonText: 'OK'
            });
        }

    } catch(err) {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.',
            confirmButtonText: 'OK'
        });
    } finally {
        btn.disabled = false;
    }
});
</script>