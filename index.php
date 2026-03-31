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

// First result set → totals
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Second result set → branch stats
// Fetch raw assignment stats (before filtering)
$stmt->nextRowset();
$assignmentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count Zero Assigned properly
$zeroAssigned = 0;
foreach ($assignmentStats as $a) {
    if ((int)$a['assigned_count'] === 0) $zeroAssigned++;
} 

// Calculate percentage

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
    ['label'=>'Total Promodizers','value'=>$total,'color'=>'primary','icon'=>'👥','link'=>'promodizers.php'],
    ['label'=>'Assigned','value'=>$assigned,'percent'=>$assignedPct,'color'=>'success','icon'=>'✅','link'=>'promodizers.php?status=active'],
    ['label'=>'Unassigned','value'=>$unassigned,'percent'=>$unassignedPct,'color'=>'danger','icon'=>'⚠️','link'=>'promodizers.php?status=inactive'],

    ['label'=>'Total Assignments','value'=>$totalAssignments,'color'=>'primary','icon'=>'📋','link'=>'assignments.php'],
    ['label'=>'Complete','value'=>$completeAssignments,'percent'=>$completePct,'color'=>'success','icon'=>'✅','link'=>'assignments.php?status=complete'],
    ['label'=>'Lacking','value'=>$lackingAssignments,'percent'=>$lackingPct,'color'=>'warning','icon'=>'⚠️','link'=>'assignments.php?status=lacking'],
    ['label'=>'Zero Assigned','value'=>$zeroAssigned,'percent'=>$zeroAssignedPct,'color'=>'danger','icon'=>'0️⃣','link'=>'assignments.php?status=zero'], // placed last
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
</style>

<div class="content">
    <div class="container-fluid">

        <h4 class="fw-bold mb-3">Dashboard</h4>

        <!-- FIRST ROW (Promodizers) -->
        <div class="row g-3 justify-content-center">
            <?php foreach(array_slice($cards,0,3) as $card): ?>
                <div class="col-md-3">
                    <a href="<?= $card['link'] ?>" class="text-decoration-none">
                        <div class="card shadow-sm border-<?= $card['color'] ?> hover-scale"
                             data-bs-toggle="tooltip"
                             title="<?= $card['label'] ?> Details">
                            <div class="card-body text-center">
                                <h6 class="text-muted"><?= $card['icon'] ?> <?= $card['label'] ?></h6>
                                <h3>
                                    <?= $card['value'] ?>
                                    <?php if(isset($card['percent'])): ?>
                                        <small class="text-muted">(<?= $card['percent'] ?>%)</small>
                                    <?php endif; ?>
                                </h3>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- SECOND ROW (Assignments including Zero Assigned) -->
        <div class="row g-3 mt-3 justify-content-center">
            <?php foreach(array_slice($cards,3,4) as $card): // now 4 cards in second row ?>
                <div class="col-md-3">
                    <a href="<?= $card['link'] ?>" class="text-decoration-none">
                        <div class="card shadow-sm border-<?= $card['color'] ?> hover-scale"
                             data-bs-toggle="tooltip"
                             title="<?= $card['label'] ?> Details">
                            <div class="card-body text-center">
                                <h6 class="text-muted"><?= $card['icon'] ?> <?= $card['label'] ?></h6>
                                <h3>
                                    <?= $card['value'] ?>
                                    <?php if(isset($card['percent'])): ?>
                                        <small class="text-muted">(<?= $card['percent'] ?>%)</small>
                                    <?php endif; ?>
                                </h3>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- CHARTS -->
        <div class="row g-3 mt-4">

            <!-- Promodizer -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Promodizer Status</h6>
                        <canvas id="promodizerChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Assignment -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Assignments Status</h6>
                        <canvas id="assignmentChart"></canvas>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/chart.js"></script>

<script>
// PROMODIZER CHART
new Chart(document.getElementById('promodizerChart'), {
    type:'doughnut',
    data:{
        labels:['Assigned','Unassigned'],
        datasets:[{
            data:[<?= $assigned ?>, <?= $unassigned ?>],
            backgroundColor:['#198754','#dc3545']
        }]
    },
    options:{plugins:{legend:{position:'bottom'}}}
});

// ASSIGNMENT CHART
new Chart(document.getElementById('assignmentChart'), {
    type:'doughnut',
    data:{
        labels:['Complete','Lacking','Zero Assigned'],
        datasets:[{
            data:[<?= $completeAssignments ?>, <?= $lackingAssignments ?>, <?= $zeroAssigned ?>],
            backgroundColor:['#198754','#ffc107','#dc3545']
        }]
    },
    options:{plugins:{legend:{position:'bottom'}}}
});
</script>

<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>