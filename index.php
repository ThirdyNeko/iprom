<?php
include 'config/db.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$current_page = basename($_SERVER['PHP_SELF']); // e.g., "index.php"

$pdo = qa_db();

/* ==============================
   DASHBOARD COUNTS
============================== */

// Total promodizers
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM employee_info");
$total = $stmt->fetch()['total'] ?? 0;

// Assigned (has branch + brand)
$stmt = $pdo->query("
    SELECT COUNT(*) AS assigned 
    FROM employee_info 
    WHERE branch IS NOT NULL AND brand IS NOT NULL
");
$assigned = $stmt->fetch()['assigned'] ?? 0;

// Unassigned
$unassigned = $total - $assigned;
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