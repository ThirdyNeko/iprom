<div class="modal fade" id="modalVacantPlantillas" tabindex="-1" aria-labelledby="modalVacantPlantillasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header" style="background-color:#2d68c4;">
                <h5 class="modal-title text-white fw-bold" id="modalVacantPlantillasLabel">
                    📭 Brand Plantilla Records
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small mb-3">Select a brand and status to generate the report.</p>

                <label class="form-label fw-semibold small">Brand</label>
                <select id="selectBrandVacant" class="form-select mb-3">
                    <option value="" disabled selected>— Select a brand —</option>
                    <option value="ALL">All Brands</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= htmlspecialchars($brand) ?>">
                            <?= htmlspecialchars($brand) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="form-label fw-semibold small">Status</label>
                <select id="selectStatusVacant" class="form-select">
                    <option value="all">All</option>
                    <option value="complete">Complete</option>
                    <option value="vacant">Vacant & Incomplete</option>
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