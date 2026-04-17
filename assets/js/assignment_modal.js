let assignmentModalDisabled = false;

// ✅ reusable status function
function getStatusBadge(required, assigned) {
    let shortage = required - assigned;

    if (assigned === 0) {
        return `<span class="badge bg-danger">INACTIVE</span>`;
    } else if (shortage > 0) {
        return `<span class="badge bg-warning">VACANT: ${shortage}</span>`;
    } else {
        return `<span class="badge bg-success">ACTIVE</span>`;
    }
}

$(document).on('click', '#assignmentTable tbody tr', function () {

    let row = $(this);

    let branch = row.data('branch');
    if (!branch) return;

    let brand    = row.data('brand');
    let required = parseInt(row.data('required')) || 0;
    let assigned = parseInt(row.data('assigned')) || 0;
    let updated  = row.data('updated');

    $('#assignmentModal').data('branch', branch);
    $('#assignmentModal').data('brand', brand);

    // ✅ status
    let status = getStatusBadge(required, assigned);

    $('#modalBranch').text(branch);
    $('#modalBrand').text(brand);
    $('#modalRequired').val(required);
    $('#modalStatus').html(status);

    // Load assigned employees
    $('#modalAssignedList').html('<small class="text-muted">Loading...</small>');

    fetch('functions/get_assigned_promodizers.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ branch, brand })
    })
    .then(res => res.json())
    .then(res => {

        if (res.status !== 'success') {
            assignmentModalDisabled = true;

            $('#modalAssignedList').html(
                '<div class="alert alert-danger mb-0">Failed to load assignments.</div>'
            );

            $('#modalRequired').prop('disabled', true);
            $('#saveRequiredBtn').prop('disabled', true);
            return;
        }

        assignmentModalDisabled = false;

        let html = '';

        if (!res.data.length) {
            html = '<small class="text-muted">No assigned employees</small>';
        } else {
            html = '<ul class="list-group list-group-flush">';

            res.data.forEach(emp => {
                html += `
                    <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                        <span>${emp.first_name} ${emp.last_name}</span>
                        <button 
                            class="btn btn-sm btn-primary edit-btn"
                            data-id="${emp.id}">
                            Edit
                        </button>
                    </li>
                `;
            });

            html += '</ul>';
        }

        // ✅ Add button if needed
        let requiredVal = parseInt($('#modalRequired').val()) || 0;
        let assignedCount = res.data.length;

        if (assignedCount < requiredVal) {
            html += `
                <div class="mt-2 text-center">
                    <a href="promodizers.php?status=inactive" 
                       class="btn btn-sm btn-primary">
                        + Add Promodizer
                    </a>
                </div>
            `;
        }

        $('#modalAssignedList').html(html);

        // ✅ update modal status dynamically after fetch
        $('#modalStatus').html(getStatusBadge(requiredVal, assignedCount));
    })
    .catch(err => {
        console.error("Fetch error:", err);

        assignmentModalDisabled = true;

        $('#modalAssignedList').html(
            '<div class="alert alert-danger mb-0">Service unavailable.</div>'
        );

        $('#modalRequired').prop('disabled', true);
        $('#saveRequiredBtn').prop('disabled', true);
    });

    let formattedDate = updated 
        ? new Date(updated.replace(' ', 'T')).toLocaleDateString('en-CA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        })
        : '-';

    $('#modalUpdated').text(formattedDate);
    $('#modalUpdatedBy').text(row.data('updated-by') || '-');

    if (assignmentModalDisabled) {
        Swal.fire({
            icon: 'error',
            title: 'Unavailable',
            text: 'Assignment service is currently unavailable.'
        });
        return;
    }

    new bootstrap.Modal(document.getElementById('assignmentModal')).show();
});


// ✅ SAVE REQUIRED
document.getElementById('saveRequiredBtn').addEventListener('click', async () => {

    if (assignmentModalDisabled) {
        return Swal.fire({
            icon: 'error',
            title: 'Unavailable',
            text: 'Cannot update right now.'
        });
    }

    const modal = $('#assignmentModal');
    const branch = modal.data('branch');
    const brand  = modal.data('brand');
    const required = parseInt($('#modalRequired').val());

    if (isNaN(required) || required < 0) {
        return Swal.fire({
            icon: 'error',
            title: 'Invalid Input',
            text: 'Required must be a valid non-negative number.'
        });
    }

    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Update Required?',
        text: `Change required count for ${branch} - ${brand}?`,
        showCancelButton: true,
        confirmButtonText: 'Yes, Update'
    });

    if (!confirm.isConfirmed) return;

    try {
        const res = await fetch('functions/update_required.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ branch, brand, required })
        });

        const result = await res.json();

        if (result.status === 'success') {

            await Swal.fire({
                icon: 'success',
                title: 'Updated!',
                timer: 1200,
                showConfirmButton: false
            });

            let row = $(`#assignmentTable tbody tr`).filter(function () {
                return $(this).data('branch') === branch &&
                       $(this).data('brand') === brand;
            });

            row.data('required', required);
            row.find('.required-cell').text(required);

            let assigned = parseInt(row.data('assigned')) || 0;

            // ✅ update status
            let status = getStatusBadge(required, assigned);
            row.find('td').eq(4).html(status);

            bootstrap.Modal.getInstance(document.getElementById('assignmentModal')).hide();
            window.assignmentTable.ajax.reload(null, false);

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

function setReadonlyState() {
    // inputs that should ALWAYS be readonly
    $('#editFirstName, #editLastName, #editBranch, #editBrand, #editDateHired, #editStatus, #editLastAssignedBy, #editAssignmentDate, #editLastUpdatedBy, #editDateLastUpdated')
        .prop('readonly', true);

    // selects (must use disabled)
    $('#editEmploymentStatus, #editReasonUpdate')
        .prop('disabled', false); // keep editable ones enabled
}

$(document).on('click', '.edit-btn', function (e) {
    e.stopPropagation();

    const id = $(this).data('id');

    window.location.href = `promodizers.php?edit=${id}`;
});