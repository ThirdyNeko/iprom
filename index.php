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

// Call stored procedure that returns all counts
$stmt = $pdo->prepare("EXEC get_dashboard_counts");
$stmt->execute();

// First result set → dashboard counts
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Move to second result set → branch-level data
$stmt->nextRowset();
$branchStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Promodizers
$total = $result['total_promodizers'];
$assigned = $result['assigned_promodizers'];
$unassigned = $result['unassigned_promodizers'];

// Assignments
$totalAssignments = $result['total_assignments'];
$completeAssignments = $result['complete_assignments'];
$lackingAssignments = $result['lacking_assignments'];
$excessAssignments = $result['excess_assignments'];

// Calculate percentages
$assignedPct = $total ? round($assigned / $total * 100, 1) : 0;
$unassignedPct = $total ? round($unassigned / $total * 100, 1) : 0;
$completePct = $totalAssignments ? round($completeAssignments / $totalAssignments * 100, 1) : 0;
$lackingPct = $totalAssignments ? round($lackingAssignments / $totalAssignments * 100, 1) : 0;
$excessPct = $totalAssignments ? round($excessAssignments / $totalAssignments * 100, 1) : 0;

// Cards data array
$cards = [
    ['label' => 'Total Promodizers', 'value' => $total, 'color' => 'primary', 'icon' => '👥', 'link' => 'promodizers.php'],
    ['label' => 'Assigned', 'value' => $assigned, 'percent' => $assignedPct, 'color' => 'success', 'icon' => '✅', 'link' => 'promodizers.php?status=assigned'],
    ['label' => 'Unassigned', 'value' => $unassigned, 'percent' => $unassignedPct, 'color' => 'danger', 'icon' => '⚠️', 'link' => 'promodizers.php?status=unassigned'],
    ['label' => 'Total Assignments', 'value' => $totalAssignments, 'color' => 'primary', 'icon' => '📋', 'link' => 'assignments.php'],
    ['label' => 'Complete', 'value' => $completeAssignments, 'percent' => $completePct, 'color' => 'success', 'icon' => '✅', 'link' => 'assignments.php?status=complete'],
    ['label' => 'Lacking', 'value' => $lackingAssignments, 'percent' => $lackingPct, 'color' => 'danger', 'icon' => '⚠️', 'link' => 'assignments.php?status=lacking'],
    ['label' => 'Excess', 'value' => $excessAssignments, 'percent' => $excessPct, 'color' => 'warning', 'icon' => '⚠️', 'link' => 'assignments.php?status=excess'],
];
?>

<style>
/* Hover effect for cards */
.hover-scale {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-scale:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    cursor: pointer;
}
</style>

<div class="content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col">
                <h4 class="fw-bold">Dashboard</h4>
            </div>
        </div>

        <div class="row g-3 justify-content-center">
            <!-- Promodizer Cards -->
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

        <div class="row g-3 mt-3 justify-content-center">
            <!-- Assignment Cards -->
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

        <!-- Charts Section: 3 charts in one row -->
        <div class="row g-3 mt-4">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">Promodizer Status</h6>
                        <canvas id="promodizerChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">Branch Assignments (Assigned vs Excess)</h6>
                        <canvas id="branchAssignmentChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">Assignments Status</h6>
                        <canvas id="assignmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Prepare branch-level arrays for Chart.js
        $branches = [];
        $completeData = [];
        $excessData = [];
        foreach ($branchStats as $row) {
            $branches[] = $row['branch_name'];
            $completeData[] = (int)$row['complete_count'];
            $excessData[] = (int)$row['excess_count'];
        }
        ?>

    </div>
</div>

<!-- JS -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize Bootstrap tooltips
document.addEventListener("DOMContentLoaded", function(){
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Charts
const promodizerCtx = document.getElementById('promodizerChart').getContext('2d');
const promodizerChart = new Chart(promodizerCtx, {
    type: 'doughnut',
    data: {
        labels: ['Assigned','Unassigned'],
        datasets:[{
            data: [<?= $assigned ?>, <?= $unassigned ?>],
            backgroundColor:['#198754','#dc3545']
        }]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom'}} }
});

const assignmentCtx = document.getElementById('assignmentChart').getContext('2d');
const assignmentChart = new Chart(assignmentCtx, {
    type:'doughnut',
    data:{
        labels:['Complete','Lacking'],
        datasets:[{
            data:[<?= $completeAssignments ?>, <?= $lackingAssignments ?>],
            backgroundColor:['#198754','#dc3545']
        }]
    },
    options:{ responsive:true, plugins:{legend:{position:'bottom'}} }
});

const branchCtx = document.getElementById('branchAssignmentChart').getContext('2d');
const branchAssignmentChart = new Chart(branchCtx,{
    type:'bar',
    data:{
        labels: <?= json_encode($branches) ?>,
        datasets:[
            {label:'Assigned', data: <?= json_encode($completeData) ?>, backgroundColor:'#198754'},
            {label:'Excess', data: <?= json_encode($excessData) ?>, backgroundColor:'#ffc107'}
        ]
    },
    options:{
        responsive:true,
        plugins:{legend:{position:'bottom'}},
        scales:{
            x:{stacked:true, title:{display:true,text:'Branches'}},
            y:{stacked:true, beginAtZero:true, title:{display:true,text:'Number of Promodizers'}}
        }
    }
});
</script>

<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>