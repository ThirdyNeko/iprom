$(document).ready(function() {
    var table = $('#promodizerTable').DataTable({
        pageLength: 10,
        responsive: true,
        dom: 'lrtip'
    });

    table.column(3).search('^(?!TERMINATED$).*$', true, false).draw();

    // Column indexes after removing ID column:
    // 0 Name, 1 Branch, 2 Brand, 3 Status, 4 Assigned By, 5 Date

    $('#filterBranch').on('change', function() {
        var val = this.value;
        table.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
    });

    $('#filterBrand').on('change', function() {
        var val = this.value;
        table.column(2).search(val ? '^' + val + '$' : '', true, false).draw();
    });
    
    $('#filterStatus').on('change', function() {
        var val = this.value;

        if (val === '') {
            // ✅ Back to default (exclude TERMINATED)
            table.column(3).search('^(?!TERMINATED$).*$', true, false);
        } else {
            // ✅ Show only selected status
            table.column(3).search('^' + val + '$', true, false);
        }

        table.draw();
    });

    $('#filterAssignedBy').on('keyup', function() {
        table.column(4).search(this.value).draw();
    });

    // DATE RANGE FILTER (custom)
    $.fn.dataTable.ext.search.push(function(settings, data) {
        var from = $('#filterFrom').val();
        var to   = $('#filterTo').val();
        var date = data[5]; // Assignment Date column

        if (!date) return true;

        var rowDate = new Date(date);
        var fromDate = from ? new Date(from) : null;
        var toDate   = to ? new Date(to) : null;

        if (
            (!fromDate || rowDate >= fromDate) &&
            (!toDate || rowDate <= toDate)
        ) {
            return true;
        }
        return false;
    });

    $('#filterFrom, #filterTo').on('change', function() {
        table.draw();
    });
});
