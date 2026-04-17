$(document).ready(function() {

    var table = $('#promodizerTable').DataTable({
        pageLength: 10,
        responsive: true,
        dom: 'lrtip'
    });

    // =========================
    // STATUS FILTER HANDLER FIRST
    // =========================
    $('#filterStatus').on('change', function() {
        var val = this.value;

        if (val === 'ACTIVE' || val === 'INACTIVE') {
            table.column(3).search('^' + val + '$', true, false);
        } else {
            table.column(3).search('');
        }

        table.draw();
    });

    // =========================
    // DEFAULT = ACTIVE
    // =========================
    $('#filterStatus').val('ACTIVE').trigger('change');

    // =========================
    // URL PARAM SUPPORT
    // =========================
    const params = new URLSearchParams(window.location.search);
    const statusParam = params.get('status');
    const editId = params.get('edit');

    if (editId) {

        table.on('draw.dt', function () {

            const row = table
                .rows()
                .nodes()
                .to$()
                .filter(function () {
                    return String($(this).data('id')) === String(editId);
                });

            if (!row.length) return;

            row.addClass('table-warning');

            // scroll
            $('html, body').animate({
                scrollTop: row.offset().top - 150
            }, 300);

            // ✅ THIS is the key
            row.trigger('click');

            table.off('draw.dt');
        });

        table.draw(false);
    }

    if (statusParam) {
        $('#filterStatus')
            .val(statusParam.toUpperCase())
            .trigger('change');
    }

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

    // Assigned By
    $('#filterAssignedBy').on('keyup', function() {
        table.column(6).search(this.value).draw();
    });

    // DATE FILTER
    $.fn.dataTable.ext.search.push(function(settings, data) {
        var from = $('#filterFrom').val();
        var to   = $('#filterTo').val();
        var date = data[7];

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

    // Employment Status
    $('#filterEmploymentStatus').on('change', function() {
        var val = this.value;
        table.column(4).search(val ? '^' + escapeRegex(val) + '$' : '', true, false).draw();
    });

    // Sub Status
    $('#filterSubStatus').on('change', function() {
        var val = this.value;
        table.column(5).search(val ? '^' + escapeRegex(val) + '$' : '', true, false).draw();
    });

    function escapeRegex(val) {
        return val.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
});