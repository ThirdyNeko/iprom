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
