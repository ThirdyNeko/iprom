$(document).ready(function() {
    function applyFiltersFromURL() {
        const params = new URLSearchParams(window.location.search);

        if (params.get('branch')) $('#filterBranch').val(params.get('branch'));
        if (params.get('brand'))  $('#filterBrand').val(params.get('brand'));
        if (params.get('status')) $('#filterStatus').val(params.get('status'));
        if (params.get('from_date')) $('#filterFrom').val(params.get('from_date'));
        if (params.get('to_date'))   $('#filterTo').val(params.get('to_date'));
    }

    applyFiltersFromURL();
    window.assignmentTable = $('#assignmentTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'functions/fetch_assignments.php',
            type: 'POST',
            data: function(d) {
                d.branch = $('#filterBranch').val();
                d.brand  = $('#filterBrand').val();
                d.status = $('#filterStatus').val();
                d.from_date = $('#filterFrom').val();
                d.to_date   = $('#filterTo').val();
            }
        },
        pageLength: 50,
        lengthMenu: [10,25,50,100],
        responsive: true,
        dom: 'lrtip',
        order: [[6,'desc']]
    });

    // Reload table on filter change
    $('#filterBranch,#filterBrand,#filterStatus,#filterFrom,#filterTo').on('change', function(){
        window.assignmentTable.ajax.reload();
    });

    // Clickable row handler
    $('#assignmentTable tbody').on('click', 'tr.clickable-row', function() {
        var branch = $(this).data('branch');
        var brand  = $(this).data('brand');
        var required = $(this).data('required');
        var assigned = $(this).data('assigned');
        var updated = $(this).data('updated');

    });

});
