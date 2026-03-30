<?php
session_start(); 
$current_page = basename($_SERVER['PHP_SELF']);

include 'config/db.php';
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
$stmt->nextRowset();
$branchStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
$excessAssignments = (int)$result['excess_assignments'];

// Percentages
$assignedPct = $total ? round($assigned / $total * 100, 1) : 0;
$unassignedPct = $total ? round($unassigned / $total * 100, 1) : 0;
$completePct = $totalAssignments ? round($completeAssignments / $totalAssignments * 100, 1) : 0;
$lackingPct = $totalAssignments ? round($lackingAssignments / $totalAssignments * 100, 1) : 0;
$excessPct = $totalAssignments ? round($excessAssignments / $totalAssignments * 100, 1) : 0;

/* ==============================
   BRANCH CHART (TOP BY EXCESS)
============================== */

$topLimit = 10;

$branches = [];
$completeData = [];
$excessData = [];

$othersComplete = 0;
$othersExcess = 0;

// Sort by EXCESS descending
usort($branchStats, function($a, $b) {
    return (int)$b['excess_count'] <=> (int)$a['excess_count'];
});

foreach ($branchStats as $index => $row) {
    if ($index < $topLimit) {
        $branches[] = $row['branch_name'];
        $completeData[] = (int)$row['complete_count'];
        $excessData[] = (int)$row['excess_count'];
    } else {
        $othersComplete += (int)$row['complete_count'];
        $othersExcess += (int)$row['excess_count'];
    }
}

// Add "Others" if any
if ($othersComplete > 0 || $othersExcess > 0) {
    $branches[] = 'Others';
    $completeData[] = $othersComplete;
    $excessData[] = $othersExcess;
}
/* ==============================
   CARDS
============================== */
$cards = [
    ['label'=>'Total Promodizers','value'=>$total,'color'=>'primary','icon'=>'👥','link'=>'promodizers.php'],
    ['label'=>'Assigned','value'=>$assigned,'percent'=>$assignedPct,'color'=>'success','icon'=>'✅','link'=>'promodizers.php?status=active'],
    ['label'=>'Unassigned','value'=>$unassigned,'percent'=>$unassignedPct,'color'=>'danger','icon'=>'⚠️','link'=>'promodizers.php?status=inactive'],
    
    ['label'=>'Total Assignments','value'=>$totalAssignments,'color'=>'primary','icon'=>'📋','link'=>'assignments.php'],
    
    // Updated to include filter-ready URL parameters
    ['label'=>'Complete','value'=>$completeAssignments,'percent'=>$completePct,'color'=>'success','icon'=>'✅','link'=>'assignments.php?status=complete'],
    ['label'=>'Lacking','value'=>$lackingAssignments,'percent'=>$lackingPct,'color'=>'danger','icon'=>'⚠️','link'=>'assignments.php?status=lacking'],
    ['label'=>'Excess','value'=>$excessAssignments,'percent'=>$excessPct,'color'=>'warning','icon'=>'⚠️','link'=>'assignments.php?status=excess']
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

        <!-- CARDS -->
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
                                        
                                    <?php endif; ?>
                                </h3>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- SECOND ROW (Assignments) -->
        <div class="row g-3 mt-3 justify-content-center">
            <?php foreach(array_slice($cards,3) as $card): ?>
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
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Promodizer Status</h6>
                        <canvas id="promodizerChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Branch -->
            <div class="col-md-4 d-flex">
                <div class="card shadow-sm w-100">
                    <div class="card-body d-flex flex-column">
                        <h6 class="text-muted">Branch Assignments</h6>
                        <div class="flex-grow-1">
                            <canvas id="branchChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignment -->
            <div class="col-md-4">
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
// PROMODIZER
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

// ASSIGNMENT (FIXED)
new Chart(document.getElementById('assignmentChart'), {
type:'doughnut',
data:{
labels:['Complete','Lacking'],
datasets:[{
    data:[<?= $completeAssignments ?>, <?= $lackingAssignments ?>],
    backgroundColor:['#198754','#dc3545']
}]
},
options:{plugins:{legend:{position:'bottom'}}}
});

<?php
$adjustedAssigned = [];
foreach ($completeData as $i => $assigned) {
    $excess = $excessData[$i];
    $adjustedAssigned[] = max(0, $assigned - $excess);
}
?>

// BRANCH (TOP 10 + OTHERS, HORIZONTAL)
new Chart(document.getElementById('branchChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($branches) ?>,
        datasets: [
            {
                label: 'Assigned',
                data: <?= json_encode($adjustedAssigned) ?>, // ✅ reduced
                backgroundColor: '#198754'
            },
            {
                label: 'Excess',
                data: <?= json_encode($excessData) ?>,
                backgroundColor: '#ffc107'
            }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        },
        scales: {
            x: { beginAtZero: true, stacked: true },
            y: { stacked: true, ticks: { autoSkip: false } }
        }
    }
});
</script>

<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>