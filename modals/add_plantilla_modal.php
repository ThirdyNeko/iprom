<?php
// Fetch branches only (no branch param, so SP returns all branches)
$stmt = $pdo->prepare("EXEC dbo.get_branches_brands @branch = NULL");
$stmt->execute();
$plantillaBranches = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="modal fade" id="addPlantillaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addPlantillaForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Plantilla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select name="branch" id="branchSelect" class="form-select" required>
                            <option value="">Select Branch</option>
                            <?php foreach($plantillaBranches as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Brand</label>
                        <select name="brand" id="brandSelect" class="form-select" required>
                            <option value="">Select Brand</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Plantilla Count</label>
                        <input type="number" name="required_count" class="form-control" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Add Plantilla</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/add_plantilla.js"></script>