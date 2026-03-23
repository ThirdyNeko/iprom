<?php
session_start(); // make sure session is started
$current_page = basename($_SERVER['PHP_SELF']); // e.g., "index.php"

include 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

/* ==============================
   DASHBOARD COUNTS
============================== */

// Total promodizers
$stmt = $pdo->prepare("EXEC get_dashboard_counts");
$stmt->execute();

$result = $stmt->fetch(PDO::FETCH_ASSOC);

$total = $result['total_promodizers'];
$assigned = $result['assigned_promodizers'];
$unassigned = $result['unassigned_promodizers'];

$totalAssignments = $result['total_assignments'];
$completeAssignments = $result['complete_assignments'];
$pendingAssignments = $result['pending_assignments'];
?>

<div class="content">

    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col">
                <h4 class="fw-bold">Dashboard</h4>
            </div>
        </div>

        <div class="row g-3">

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Total Promodizers</h6>
                        <h3><?= $total ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Assigned</h6>
                        <h3><?= $assigned ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Unassigned</h6>
                        <h3><?= $unassigned ?></h3>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-3 mt-3">

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Total Plantilla</h6>
                        <h3><?= $totalAssignments ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Complete</h6>
                        <h3><?= $completeAssignments ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Pending</h6>
                        <h3><?= $pendingAssignments ?></h3>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- JS -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<?php include 'modals/change_password_modal.php'; ?>


</body>
</html>