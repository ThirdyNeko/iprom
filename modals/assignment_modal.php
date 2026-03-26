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
                            <td id="modalRequired"></td>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<script>
$(document).on('click', '#assignmentTable tbody tr', function () {

    let row = $(this);

    let branch   = row.data('branch');
    if (!branch) return;

    let brand    = row.data('brand');
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
    $('#modalRequired').text(required);
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
</script>

