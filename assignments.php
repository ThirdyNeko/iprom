<?php
session_start();
include 'config/db.php';

$current_page = basename($_SERVER['PHP_SELF']);

include 'partials/header.php';
include 'partials/sidebar.php';

$pdo = qa_db();

/* =========================
   FETCH ASSIGNMENTS
========================= */
$branch = $_GET['branch'] ?? null;
$brand  = $_GET['brand'] ?? null;
$id     = $_GET['id'] ?? null;
$status = $_GET['status'] ?? null; // <<< add this

$stmt = $pdo->prepare("EXEC get_assignments 
    @branch_name = :branch,
    @brand_name = :brand,
    @assignment_id = :id
");

$stmt->bindValue(':branch', $branch);
$stmt->bindValue(':brand', $brand);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);

$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter by status if requested (complete/lacking/excess)
if($status){
    $assignments = array_filter($assignments, function($a) use($status){
        $shortage = $a['required_count'] - $a['assigned_count'];
        return ($status==='complete' && $shortage<=0)
            || ($status==='lacking' && $shortage>0)
            || ($status==='excess' && $shortage<0);
    });
}

?>

<div class="content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col">
                <h4 class="fw-bold">Assignments</h4>
            </div>
            <div class="col text-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlantillaModal">
                    <i class="bi bi-plus-circle"></i> Add Plantilla
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">

                    <table id="assignmentTable" class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Branch</th>
                                <th>Brand</th>
                                <th>Required</th>
                                <th>Assigned</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach($assignments as $index => $a): 
                                $shortage = $a['required_count'] - $a['assigned_count'];
                            ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= $a['branch_name'] ?></td>
                                    <td><?= $a['brand_name'] ?></td>
                                    <td><?= $a['required_count'] ?></td>
                                    <td><?= $a['assigned_count'] ?></td>
                                    <td>
                                        <?php if($shortage>0): ?>
                                            <span class="badge bg-danger">Needs <?= $shortage ?></span>
                                        <?php elseif($shortage<0): ?>
                                            <span class="badge bg-warning">Excess <?= -$shortage ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Complete</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>

                </div>
            </div>
        </div>

    </div>
</div>

<!-- JS -->
<script src="assets/js/jquery-4.0.0.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    $('#assignmentTable').DataTable({
        "pageLength": 10,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true
    });
});
</script>

<?php include 'modals/add_plantilla_modal.php'; ?>
<?php include 'modals/change_password_modal.php'; ?>


</body>
</html>