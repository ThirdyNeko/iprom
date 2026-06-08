<div class="modal fade" id="modalVacantPlantillas" tabindex="-1" aria-labelledby="modalVacantPlantillasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header" style="background-color:#2d68c4;">
                <h5 class="modal-title text-white fw-bold" id="modalVacantPlantillasLabel">
                    📭 Vacant & Incomplete Plantillas Report
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small mb-3">Select a brand to generate the report.</p>
                <select id="selectBrandVacant" class="form-select">
                    <option value="" disabled selected>— Select a brand —</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= htmlspecialchars($brand) ?>">
                            <?= htmlspecialchars($brand) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnGenerateVacantPlantillas"
                        onclick="generateReport('vacant_plantillas')">
                    Generate Report
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>                
            </div>

        </div>
    </div>
</div>