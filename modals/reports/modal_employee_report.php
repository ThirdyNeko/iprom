<div class="modal fade" id="modalEmployeeReport" tabindex="-1" aria-labelledby="modalEmployeeReportLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header" style="background-color:#2d68c4;">
                <h5 class="modal-title text-white fw-bold" id="modalEmployeeReportLabel">
                    👤 Promodiser List
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small mb-3">Select a branch to generate the report.</p>
                <select id="selectBranch" class="form-select">
                    <option value="" disabled selected>— Select a branch —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= htmlspecialchars($b['branch_code']) ?>">
                            <?= htmlspecialchars($b['branch'] ?? $b['branch_code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnGenerateEmployee"
                        onclick="generateReport('employee_report')">
                    Generate Report
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>                
            </div>

        </div>
    </div>
</div>