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
// Before (no filtering):
$stmt = $pdo->prepare("EXEC get_dashboard_counts");
$stmt->execute();

// After (with branch filtering):
$isStaff         = isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
$sessionBranches = !empty($_SESSION['branch']) ? $_SESSION['branch'] : null;
if ($isStaff && $sessionBranches === null) {
    $result = [
        'total_promodizers' => 0, 'active_promodizers' => 0, 'inactive_promodizers' => 0,
        'total_assignments' => 0, 'complete_assignments' => 0,
        'lacking_assignments' => 0, 'zero_assigned' => 0,
    ];
} else {
    $stmt = $pdo->prepare("EXEC get_dashboard_counts @branches = ?");
    $stmt->execute([$isStaff ? $sessionBranches : null]); // null = all branches
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
}

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
    ['label'=>'COMPLETE','value'=>$completeAssignments,'percent'=>$completePct,'color'=>'success','icon'=>'✅','link'=>'assignments.php?status=complete'],
    ['label'=>'INCOMPLETE','value'=>$lackingAssignments,'percent'=>$lackingPct,'color'=>'orange','icon'=>'⚠️','link'=>'assignments.php?status=lacking'],
    ['label'=>'VACANT','value'=>$zeroAssigned,'percent'=>$zeroAssignedPct,'color'=>'danger','icon'=>'0️⃣','link'=>'assignments.php?status=zero'], // placed last
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

.border-orange{
    border-color: #ffd700 !important;
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

                                <div class="card <?= $card['color'] ?> border-<?= $card['color'] ?> hover-scale h-100"
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

                                <div class="card <?= $card['color'] ?> border-<?= $card['color'] ?> hover-scale h-100"
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
// PROMODIZER CHART
const noDataPlugin = {
    id: 'noDataPlugin',
    afterDraw(chart) {
        const data = chart.data.datasets[0].data;

        if (data.every(value => value === 0)) {
            const { ctx, chartArea } = chart;

            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = '18px Arial';
            ctx.fillStyle = '#9fa2a5';

            ctx.fillText(
                'No Data Available',
                (chartArea.left + chartArea.right) / 2,
                (chartArea.top + chartArea.bottom) / 2
            );

            ctx.restore();
        }
    }
};

Chart.register(noDataPlugin);
// PROMODIZER CHART
new Chart(document.getElementById('promodizerChart'), {
    type: 'doughnut',
    data: {
        labels: ['ACTIVE', 'INACTIVE'],
        datasets: [{
            data: [<?= $assigned ?>, <?= $unassigned ?>],
            backgroundColor: ['#198754', '#dc3545']
        }]
    },
    options: {
        plugins: {
            legend: {
                position: 'bottom',
                display: ![<?= $assigned ?>, <?= $unassigned ?>].every(v => v === 0)
            }
        },
        responsive: true,
        maintainAspectRatio: false
    }
});

// ASSIGNMENT CHART
new Chart(document.getElementById('assignmentChart'), {
    type: 'doughnut',
    data: {
        labels: ['COMPLETE', 'INCOMPLETE', 'VACANT'],
        datasets: [{
            data: [<?= $completeAssignments ?>, <?= $lackingAssignments ?>, <?= $zeroAssigned ?>],
            backgroundColor: ['#198754', '#ffd700', '#dc3545']
        }]
    },
    options: {
        plugins: {
            legend: {
                position: 'bottom',
                display: ![<?= $assigned ?>, <?= $unassigned ?>].every(v => v === 0)
            }
        },
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>

<?php include 'modals/change_password_modal.php'; ?>
</body>
</html>