<div class="modal fade" id="changePasswordModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
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
                            <i class="bi bi-eye-slash"></i>
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <span class="input-group-text toggle-password" data-target="new_password" style="cursor:pointer;">
                            <i class="bi bi-eye-slash"></i>
                        </span>
                    </div>
                    <ul id="passwordChecklist" class="list-unstyled small mt-2 mb-0">
                        <li data-check="length"><i class="bi bi-x-circle text-danger"></i> At least 12 characters</li>
                        <li data-check="upper"><i class="bi bi-x-circle text-danger"></i> One uppercase letter</li>
                        <li data-check="lower"><i class="bi bi-x-circle text-danger"></i> One lowercase letter</li>
                        <li data-check="number"><i class="bi bi-x-circle text-danger"></i> One number</li>
                        <li data-check="special"><i class="bi bi-x-circle text-danger"></i> One special character</li>
                    </ul>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <span class="input-group-text toggle-password" data-target="confirm_password" style="cursor:pointer;">
                            <i class="bi bi-eye-slash"></i>
                        </span>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
  </div>
</div>



<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script src="assets/js/password/change_password.js"></script>

<script>
const isFirstLogin = <?= json_encode(!empty($_SESSION['first_login'])) ?>;

document.addEventListener("DOMContentLoaded", function () {
  const modalEl = document.getElementById('changePasswordModal');

  const modal = new bootstrap.Modal(modalEl, {
    backdrop: isFirstLogin ? 'static' : true,
    keyboard: !isFirstLogin
  });

  if (isFirstLogin) {
    modal.show();

    modalEl.addEventListener('hide.bs.modal', function (e) {
      e.preventDefault();
    });
  }
});

document.getElementById('new_password').addEventListener('input', function () {
  const pw = this.value;

  const checks = {
    length: pw.length >= 12,
    upper: /[A-Z]/.test(pw),
    lower: /[a-z]/.test(pw),
    number: /[0-9]/.test(pw),
    special: /[^A-Za-z0-9]/.test(pw),
  };

  for (const [key, passed] of Object.entries(checks)) {
    const li = document.querySelector(`#passwordChecklist li[data-check="${key}"]`);
    const icon = li.querySelector('i');

    if (passed) {
      icon.className = 'bi bi-check-circle text-success';
    } else {
      icon.className = 'bi bi-x-circle text-danger';
    }
  }
});
</script>
