const branchSelect = document.getElementById('branchSelect');
const brandSelect  = document.getElementById('brandSelect');
const form         = document.getElementById('addPlantillaForm');

branchSelect.addEventListener('change', async () => {
    const branch = branchSelect.value.trim();
    brandSelect.innerHTML = '<option value="">Loading...</option>';

    if (!branch) {
        brandSelect.innerHTML = '<option value="">Select Brand</option>';
        return;
    }

    try {
        // Fetch unused brands for selected branch using the SP
        const res = await fetch(`functions/get_available_brands.php?branch=${encodeURIComponent(branch)}`);
        const data = await res.json();

        brandSelect.innerHTML = '<option value="">Select Brand</option>';
        if (data.length === 0) {
            brandSelect.innerHTML = '<option value="">No available brands</option>';
            return;
        }

        data.forEach(brand => {
            const option = document.createElement('option');
            option.value = brand;
            option.textContent = brand;
            brandSelect.appendChild(option);
        });

    } catch (err) {
        console.error(err);
        brandSelect.innerHTML = '<option value="">Error loading brands</option>';
    }
});

form.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;

    const formData = new FormData(form);
    const branch = branchSelect.value.trim();
    const brand  = brandSelect.value.trim();

    if (!branch || !brand) {
        Swal.fire({ icon: 'warning', title: 'Required Fields', text: 'Please select both Branch and Brand.' });
        btn.disabled = false;
        return;
    }

    try {
        // Confirm action
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
        if (!confirm.isConfirmed) return btn.disabled = false;

        // Submit plantilla
        const submitRes = await fetch('functions/add_plantilla.php', { method: 'POST', body: formData });
        const submitData = await submitRes.json();

        if(submitData.success){
            Swal.fire({ icon: 'success', title: 'Plantilla Added!', text: `Branch "${branch}" and Brand "${brand}" has been added successfully.` })
                .then(() => {
                    form.reset();
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addPlantillaModal'));
                    modal.hide();
                    window.assignmentTable.ajax.reload();
                });
        } else {
            Swal.fire({ icon: 'error', title: 'Error!', text: submitData.message || 'Failed to add plantilla.' });
        }

    } catch (err) {
        console.error(err);
        Swal.fire({ icon: 'error', title: 'Unexpected Error', text: 'An unexpected error occurred. Please try again.' });
    } finally {
        btn.disabled = false;
    }
});
