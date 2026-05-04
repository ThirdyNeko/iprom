<?php
session_start();
$current_page = basename($_SERVER['PHP_SELF']);
include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

$sql = "
SELECT 
    h.id,
    h.reason_for_update,
    h.update_date,
    h.remarks,
    h.employee_id,
    h.updated_by,
    i.first_name,
    i.last_name
FROM employee_reason_history h
LEFT JOIN employee_info i 
    ON h.employee_id = i.employee_id
ORDER BY h.update_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

                        <tbody>
                        <?php foreach ($logs as $row): ?>
                            <?php
                                // Safe helper (local)
                                $e = function($val, $default = '-') {
                                    return htmlspecialchars($val ?? $default);
                                };

                                // Updated By (fallback: SYSTEM if null or empty)
                                $updatedBy = !empty($row['updated_by']) ? $row['updated_by'] : 'SYSTEM';

                                // Remarks
                                $remarks = $row['remarks'] ?? '-';

                                // Reason
                                $reason = $row['reason_for_update'] ?? '-';

                                // Employee full name
                                $first = $row['first_name'] ?? '';
                                $last  = $row['last_name'] ?? '';
                                $fullName = trim("$first $last");
                                if ($fullName === '') {
                                    $fullName = '-';
                                }

                                // Date
                                $date = !empty($row['update_date'])
                                    ? date('Y-m-d', strtotime($row['update_date']))
                                    : '-';
                            ?>

                            <tr>
                                <td><?= $e($updatedBy) ?></td>
                                <td><?= $e($reason) ?></td>
                                <td><?= $e($remarks) ?></td>
                                <td><?= $e($fullName) ?></td>
                                <td><?= $e($date) ?></td>
                            </tr>

                        <?php endforeach; ?>
                        </tbody>

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
        pageLength: 10,
        order: [[4, 'desc']], // sort by update date
        responsive: true
        dom: "lrtip",
    });
});
</script>