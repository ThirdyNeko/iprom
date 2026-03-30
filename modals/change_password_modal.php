<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
        <form id="changePasswordForm">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <div id="passwordAlert"></div> <!-- For success/error messages -->

                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                        <span class="input-group-text toggle-password" data-target="current_password" style="cursor:pointer;">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <span class="input-group-text toggle-password" data-target="new_password" style="cursor:pointer;">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <span class="input-group-text toggle-password" data-target="confirm_password" style="cursor:pointer;">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Change Password</button>
            </div>
        </form>
    </div>
  </div>
</div>

<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script>
document.querySelectorAll('.toggle-password').forEach(span => {
    span.addEventListener('click', () => {
        const target = document.getElementById(span.dataset.target);
        const icon = span.querySelector('i');
        if (target.type === 'password') {
            target.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            target.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
});

// AJAX form submission with SweetAlert
document.getElementById('changePasswordForm').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    fetch('functions/change_password.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        Swal.fire({
            icon: data.status === 'success' ? 'success' : 'error',
            title: data.status === 'success' ? 'Password Changed!' : 'Oops...',
            text: data.message,
            confirmButtonText: 'OK'
        });

        if(data.status === 'success') this.reset();
    })
    .catch(err => {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Something went wrong. Please try again.',
            confirmButtonText: 'OK'
        });
    })
    .finally(() => submitBtn.disabled = false);
});
</script>