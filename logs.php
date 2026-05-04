<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

?>
<style>
    table td {
        text-align: left !important;
    } 
    #logsTable th,
    #logsTable td {
        border-right: 1px solid #dee2e6;
    }

    #logsTable th:last-child,
    #logsTable td:last-child {
        border-right: none; /* remove extra line at end */
    }
</style>
<div class="content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col">
                <h4 class="fw-bold mb-0">Employee Logs</h4>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">

                <div class="table-responsive">
                    <table id="logsTable" class="table table-striped table-hover align-middle text-center">
                        <thead class="table-dark">
                            <tr>
                                <th>User</th>                                
                                <th>Reason</th>
                                <th>Remarks</th>   
                                <th>Employee</th>                             
                                <th>Update Date</th>
                            </tr>
                        </thead>

                        <tbody></tbody>

                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function () {
    $('#logsTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 10,
        responsive: true,
        dom: "lrtip",

        ajax: {
            url: "functions/fetch_logs.php",
            type: "POST"
        },

        order: [[4, 'desc']]
    });
});
</script>