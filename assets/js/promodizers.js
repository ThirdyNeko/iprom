$(document).ready(function() {
    var table = $('#promodizerTable').DataTable({
        pageLength: 10,
        responsive: true,
        dom: 'lrtip'
    });

    // =========================
    // DEFAULT = ACTIVE only
    // =========================
    table.column(3).search('^ACTIVE$', true, false).draw();
    $('#filterStatus').val('ACTIVE');

    // Branch filter
    $('#filterBranch').on('change', function() {
        var val = this.value;
        table.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
    });

    // Brand filter
    $('#filterBrand').on('change', function() {
        var val = this.value;
        table.column(2).search(val ? '^' + val + '$' : '', true, false).draw();
    });

    // =========================
    // STATUS FILTER (FIXED)
    // =========================
    $('#filterStatus').on('change', function() {
        var val = this.value;

        if (val === 'ACTIVE' || val === 'INACTIVE') {
            table.column(3).search('^' + val + '$', true, false);
        } else {
            // ALL → show everything INCLUDING inactive
            table.column(3).search('');
        }

        table.draw();
    });

    // Assigned By
    $('#filterAssignedBy').on('keyup', function() {
        table.column(4).search(this.value).draw();
    });

    // DATE FILTER
    $.fn.dataTable.ext.search.push(function(settings, data) {
        var from = $('#filterFrom').val();
        var to   = $('#filterTo').val();
        var date = data[5];

        if (!date) return true;

        var rowDate = new Date(date);
        var fromDate = from ? new Date(from) : null;
        var toDate   = to ? new Date(to) : null;

        return (!fromDate || rowDate >= fromDate) &&
               (!toDate || rowDate <= toDate);
    });

    $('#filterFrom, #filterTo').on('change', function() {
        table.draw();
    });
});