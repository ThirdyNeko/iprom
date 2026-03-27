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
                            <td>
                                <div id="modalAssignedList" style="max-height: 200px; overflow-y: auto;">
                                    <small class="text-muted">Loading...</small>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td id="modalStatus"></td>
                            <th>Updated At</th>
                            <td id="modalUpdated"></td>
                        </tr>
                        <tr>
                            <th>Updated By</th>
                            <td colspan="3" id="modalUpdatedBy"></td> <!-- ✅ new row -->
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
    // Load assigned employees
    $('#modalAssignedList').html('<small class="text-muted">Loading...</small>');

    fetch('functions/get_assigned_promodizers.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            branch: branch,
            brand: brand
        })
    })
    .then(res => res.json())
    .then(res => {

        if (res.status !== 'success') {
            $('#modalAssignedList').html('<span class="text-danger">Failed to load</span>');
            return;
        }

        if (!res.data.length) {
            $('#modalAssignedList').html('<small class="text-muted">No assigned employees</small>');
            return;
        }

        let html = '<ul class="list-group list-group-flush">';

        res.data.forEach(emp => {
            html += `
                <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                    <span>
                        ${emp.first_name} ${emp.last_name}
                    </span>
                    <button 
                        class="btn btn-sm btn-warning unassign-btn"
                        data-id="${emp.id}"
                        data-name="${emp.first_name} ${emp.last_name}"
                    >
                        Unassign
                    </button>
                </li>
            `;
        });
        html += '</ul>';

        $('#modalAssignedList').html(html);

    })
    .catch(err => {
        console.error("Fetch error:", err);
        $('#modalAssignedList').html('<span class="text-danger">Error loading data</span>');
    });
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
    let updatedBy = row.data('updated-by') || '-';
    $('#modalUpdatedBy').text(updatedBy);

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
            window.assignmentTable.ajax.reload(null, false); // refresh table, keep current page

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

$(document).on('click', '.unassign-btn', async function () {

    const btn = $(this);
    const id = btn.data('id');
    const name = btn.data('name');

    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Unassign Employee?',
        text: `Remove ${name} from this assignment?`,
        showCancelButton: true,
        confirmButtonText: 'Yes, Unassign',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#f0ad4e',
        reverseButtons: true
    });

    if (!confirm.isConfirmed) return;

    try {
        const res = await fetch('functions/unassign_promodizer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id
            })
        });

        const result = await res.json();

        if (result.status === 'success') {

            await Swal.fire({
                icon: 'success',
                title: 'Unassigned',
                text: result.message || `${name} has been unassigned.`,
                timer: 1200,
                showConfirmButton: false
            });

            // ✅ Remove from UI instantly (NO MODAL CLOSE)
            btn.closest('li').remove();

            // ✅ Update assigned count (optional)
            let currentCount = $('#modalAssignedList li').length;
            $('#modalAssignedCount').text(`Assigned: ${currentCount}`);

            // ✅ ALSO update table row assigned count
            let modal = $('#assignmentModal');
            let branch = modal.data('branch');
            let brand  = modal.data('brand');

            let row = $(`#assignmentTable tbody tr`).filter(function () {
                return $(this).data('branch') === branch &&
                       $(this).data('brand') === brand;
            });

            let assigned = parseInt(row.data('assigned')) - 1;
            row.data('assigned', assigned);

            // update status too 🔥
            let required = parseInt(row.data('required'));
            let shortage = required - assigned;

            let status = '';
            if (shortage > 0) {
                status = `<span class="badge bg-danger">Needs ${shortage}</span>`;
            } else if (shortage < 0) {
                status = `<span class="badge bg-warning">Excess ${Math.abs(shortage)}</span>`;
            } else {
                status = `<span class="badge bg-success">Complete</span>`;
            }

            row.find('td').eq(4).html(status);
            window.assignmentTable.ajax.reload(null, false);

        } else {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: result.message || 'Unassign failed.'
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

