<div class="modal fade" id="modalCompletePlantillas" tabindex="-1" aria-labelledby="modalCompletePlantillasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header" style="background-color:#2d68c4;">
                <h5 class="modal-title text-white fw-bold" id="modalCompletePlantillasLabel">
                    📋 Complete Plantillas Report
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small mb-3">Select a brand to generate the report for.</p>
                <label for="selectBrandComplete" class="form-label fw-semibold">Brand</label>
                <select id="selectBrandComplete" class="form-select">
                    <option value="" disabled selected>— Select a brand —</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= htmlspecialchars($brand) ?>">
                            <?= htmlspecialchars($brand) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnGenerateCompletePlantillas"
                        onclick="generateReport('complete_plantillas')">
                    Generate Report
                </button>
            </div>

        </div>
    </div>
</div>