<div class="modal fade" id="modalBranchPlantillas" tabindex="-1" aria-labelledby="modalBranchPlantillasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header" style="background-color:#2d68c4;">
                <h5 class="modal-title text-white fw-bold" id="modalBranchPlantillasLabel">
                    🏪 Branch Plantilla Records
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small mb-3">Select a branch and status to generate the report.</p>

                <label class="form-label fw-semibold small">Branch</label>
                <select id="selectBranchPlantillas" class="form-select mb-3">
                    <option value="" disabled selected>— Select a branch —</option>
                    <option value="ALL">All Branches</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= htmlspecialchars($b['branch_code']) ?>">
                            <?= htmlspecialchars($b['branch']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="form-label fw-semibold small">Status</label>
                <select id="selectStatusBranchPlantillas" class="form-select">
                    <option value="all">All</option>
                    <option value="complete">Complete</option>
                    <option value="vacant">Vacant & Incomplete</option>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnGenerateBranchPlantillas"
                        onclick="generateReport('branch_plantillas')">
                    Generate Report
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>

        </div>
    </div>
</div>