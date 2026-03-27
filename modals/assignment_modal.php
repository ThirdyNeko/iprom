<div class="modal fade" id="assignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg"> <!-- ✅ wider like promodizer -->
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Assignment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- Optional alert placeholder -->
                <div id="assignmentAlert"></div>

                <!-- Assignment Info Table -->
                <table class="table table-bordered table-striped table-sm mb-3">
                    <tbody>
                        <tr>
                            <th>Branch</th>
                            <td id="modalBranch"></td>
                            <th>Brand</th>
                            <td id="modalBrand"></td>
                        </tr>
                        <tr>
                            <th>Required</th>
                            <td>
                                <input type="number" id="modalRequired" class="form-control form-control-sm" min="0">
                            </td>
                            <th>Assigned</th>
                            <td id="modalAssigned"></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td id="modalStatus"></td>
                            <th>Updated At</th>
                            <td id="modalUpdated"></td>
                        </tr>
                    </tbody>
                </table>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="saveRequiredBtn">Save</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script>
$(document).on('click', '#assignmentTable tbody tr', function () {

    let row = $(this);

    let branch   = row.data('branch');
    if (!branch) return;

    let brand    = row.data('brand');
    $('#assignmentModal').data('branch', branch);
    $('#assignmentModal').data('brand', brand);

    let required = parseInt(row.data('required'));
    let assigned = parseInt(row.data('assigned'));
    let updated  = row.data('updated');

    let shortage = required - assigned;

    let status = '';
    if (shortage > 0) {
        status = `<span class="badge bg-danger">Needs ${shortage}</span>`;
    } else if (shortage < 0) {
        status = `<span class="badge bg-warning">Excess ${Math.abs(shortage)}</span>`;
    } else {
        status = `<span class="badge bg-success">Complete</span>`;
    }

    $('#modalBranch').text(branch);
    $('#modalBrand').text(brand);
    $('#modalRequired').val(required);
    $('#modalAssigned').text(assigned);
    $('#modalStatus').html(status); // ⚠️ use html for badge
    let formattedDate = updated 
        ? new Date(updated).toLocaleString('en-CA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        }).replace(',', '')
        : '-';

    $('#modalUpdated').text(formattedDate);

    $('#assignmentModal').modal('show');
});

document.getElementById('saveRequiredBtn').addEventListener('click', async () => {

    const modal = $('#assignmentModal');

    const branch = modal.data('branch');
    const brand  = modal.data('brand');
    const required = parseInt($('#modalRequired').val());

    // ✅ validation
    if (isNaN(required) || required < 0) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Input',
            text: 'Required must be a valid non-negative number.'
        });
        return;
    }

    // ✅ confirmation dialog
    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Update Required?',
        text: `This will change the required count for ${branch} - ${brand}.`,
        showCancelButton: true,
        confirmButtonText: 'Yes, Update',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3085d6',
        reverseButtons: true
    });

    if (!confirm.isConfirmed) return;

    try {
        const res = await fetch('functions/update_required.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                branch: branch,
                brand: brand,
                required: required
            })
        });

        const result = await res.json();

        if (result.status === 'success') {

            await Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: result.message || 'Required count updated successfully.'
            });

            // ✅ update table live
            let row = $(`#assignmentTable tbody tr`).filter(function () {
                return $(this).data('branch') === branch &&
                       $(this).data('brand') === brand;
            });

            row.data('required', required);
            row.find('.required-cell').text(required);

            // ✅ OPTIONAL: update status instantly
            let assigned = parseInt(row.data('assigned'));
            let shortage = required - assigned;

            let status = '';
            if (shortage > 0) {
                status = `<span class="badge bg-danger">Needs ${shortage}</span>`;
            } else if (shortage < 0) {
                status = `<span class="badge bg-warning">Excess ${Math.abs(shortage)}</span>`;
            } else {
                status = `<span class="badge bg-success">Complete</span>`;
            }

            row.find('td').eq(4).html(status); // status column

            // close modal
            bootstrap.Modal.getInstance(document.getElementById('assignmentModal')).hide();

        } else {
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: result.message || 'Something went wrong.'
            });
        }

    } catch (err) {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Server error occurred.'
        });
    }
});
</script>

