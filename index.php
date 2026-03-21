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

$total = $result['total'] ?? 0;
$assigned = $result['assigned'] ?? 0;
$unassigned = $result['unassigned'] ?? 0;
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

    </div>

</div>

<!-- Bootstrap JS -->
<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar() {
    document.body.classList.toggle('collapsed');
}
</script>

</body>
</html>