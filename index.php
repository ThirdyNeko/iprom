<?php
session_start(); 
$current_page = basename($_SERVER['PHP_SELF']);

include 'config/db.php';
include 'auth/require_login.php';
include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

/* ==============================
   DASHBOARD COUNTS
============================== */
$stmt = $pdo->prepare("EXEC get_dashboard_counts");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

/* ==============================
   DATA PREPARATION
============================== */

// Promodizers
$total = (int)$result['total_promodizers'];
$assigned = (int)$result['active_promodizers'];
$unassigned = (int)$result['inactive_promodizers'];

// Assignments
$totalAssignments = (int)$result['total_assignments'];
$completeAssignments = (int)$result['complete_assignments'];
$lackingAssignments = (int)$result['lacking_assignments'];
$zeroAssigned = (int)$result['zero_assigned'];

// Percentages
$assignedPct = $total ? round($assigned / $total * 100, 1) : 0;
$unassignedPct = $total ? round($unassigned / $total * 100, 1) : 0;
$completePct = $totalAssignments ? round($completeAssignments / $totalAssignments * 100, 1) : 0;
$lackingPct = $totalAssignments ? round($lackingAssignments / $totalAssignments * 100, 1) : 0;
$zeroAssignedPct = $totalAssignments ? round($zeroAssigned / $totalAssignments * 100, 1) : 0;

/* ==============================
   CARDS
============================== */
$cards = [
    ['label'=>'Total Promodisers','value'=>$total,'color'=>'primary','icon'=>'👥','link'=>'promodizers.php'],
    ['label'=>'ACTIVE','value'=>$assigned,'percent'=>$assignedPct,'color'=>'success','icon'=>'✅','link'=>'promodizers.php?status=active'],
    ['label'=>'INACTIVE','value'=>$unassigned,'percent'=>$unassignedPct,'color'=>'danger','icon'=>'⚠️','link'=>'promodizers.php?status=inactive'],

    ['label'=>'Total Assignments','value'=>$totalAssignments,'color'=>'primary','icon'=>'📋','link'=>'assignments.php'],
    ['label'=>'ACTIVE','value'=>$completeAssignments,'percent'=>$completePct,'color'=>'success','icon'=>'✅','link'=>'assignments.php?status=complete'],
    ['label'=>'VACANT','value'=>$lackingAssignments,'percent'=>$lackingPct,'color'=>'warning','icon'=>'⚠️','link'=>'assignments.php?status=lacking'],
    ['label'=>'INACTIVE','value'=>$zeroAssigned,'percent'=>$zeroAssignedPct,'color'=>'danger','icon'=>'0️⃣','link'=>'assignments.php?status=zero'], // placed last
];
?>

<style>
.hover-scale {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-scale:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}
.chart-container {
    position: relative;
    height: 580px;   /* stable dashboard height */
    width: 100%;
}
</style>

<div class="content">
    <div class="container-fluid">

        <h4 class="fw-bold mb-4">Dashboard</h4>

        <div class="row g-4">

            <!-- LEFT SIDE -->
            <div class="col-12 col-lg-6 d-flex flex-column">

                <!-- ONE ROW ONLY -->
                <div class="row g-3 mb-5">

                    <?php foreach(array_slice($cards,0,3) as $card): ?>

                        <div class="col-4">

                            <a href="<?= $card['link'] ?>" class="text-decoration-none">

                                <div class="card shadow-sm border-<?= $card['color'] ?> hover-scale h-100"
                                     data-bs-toggle="tooltip"
                                     title="<?= $card['label'] ?> Details">

                                    <div class="card-body text-center py-2">

                                        <small class="text-muted d-block">
                                            <?= $card['icon'] ?> <?= $card['label'] ?>
                                        </small>

                                        <h5 class="mb-0">
                                            <?= $card['value'] ?>

                                            <?php if(isset($card['percent'])): ?>
                                                <small class="text-muted">
                                                    (<?= $card['percent'] ?>%)
                                                </small>
                                            <?php endif; ?>
                                        </h5>

                                    </div>

                                </div>

                            </a>

                        </div>

                    <?php endforeach; ?>

                </div>

                <!-- LEFT CHART -->
                <div class="card shadow-sm flex-fill">
                    <div class="card-body">
                        <small class="text-muted">Promodiser Status</small>

                        <div class="chart-container">
                            <canvas id="promodizerChart"></canvas>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT SIDE -->
            <div class="col-12 col-lg-6 d-flex flex-column">

                <!-- ONE ROW ONLY -->
                <div class="row g-3 mb-5">

                    <?php foreach(array_slice($cards,3,4) as $card): ?>

                        <div class="col-3">

                            <a href="<?= $card['link'] ?>" class="text-decoration-none">

                                <div class="card shadow-sm border-<?= $card['color'] ?> hover-scale h-100"
                                     data-bs-toggle="tooltip"
                                     title="<?= $card['label'] ?> Details">

                                    <div class="card-body text-center py-2">

                                        <small class="text-muted d-block">
                                            <?= $card['icon'] ?> <?= $card['label'] ?>
                                        </small>

                                        <h5 class="mb-0">
                                            <?= $card['value'] ?>

                                            <?php if(isset($card['percent'])): ?>
                                                <small class="text-muted">
                                                    (<?= $card['percent'] ?>%)
                                                </small>
                                            <?php endif; ?>
                                        </h5>

                                    </div>

                                </div>

                            </a>

                        </div>

                    <?php endforeach; ?>

                </div>

                <!-- RIGHT CHART -->
                <div class="card shadow-sm flex-fill">
                    <div class="card-body">
                        <small class="text-muted">Assignments Status</small>

                        <div class="chart-container">
                            <canvas id="assignmentChart"></canvas>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/dashboard/chart.js"></script>

<script>
  const isFirstLogin = <?= json_encode(!empty($_SESSION['first_login'])) ?>;

  document.addEventListener("DOMContentLoaded", function () {
    if (isFirstLogin) {
      const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
      modal.show();
    }
  });
</script>

<script>
// PROMODIZER CHART
new Chart(document.getElementById('promodizerChart'), {
    type:'doughnut',
    data:{
        labels:['ACTIVE','INACTIVE'],
        datasets:[{
            data:[<?= $assigned ?>, <?= $unassigned ?>],
            backgroundColor:['#198754','#dc3545']
        }]
    },
    options:{plugins:{legend:{position:'bottom'}}, responsive: true, maintainAspectRatio: false}
});

// ASSIGNMENT CHART
new Chart(document.getElementById('assignmentChart'), {
    type:'doughnut',
    data:{
        labels:['ACTIVE','VACANT','INACTIVE'],
        datasets:[{
            data:[<?= $completeAssignments ?>, <?= $lackingAssignments ?>, <?= $zeroAssigned ?>],
            backgroundColor:['#198754','#ffc107','#dc3545']
        }]
    },
    options:{plugins:{legend:{position:'bottom'}}, responsive: true, maintainAspectRatio: false}
});
</script>

<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>