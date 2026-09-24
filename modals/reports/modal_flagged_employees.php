<div class="modal fade" id="modalFlaggedEmployees" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background-color:#2d68c4;">
        <h5 class="modal-title text-white fw-bold">
          🚩 Flagged Employees
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Generate employees with active flags</p>
        <div class="mb-3">
          <label class="form-label">Branch</label>
          <select id="selectBranchFlaggedEmployees" class="form-select">
            <option value="ALL">All Branches</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= htmlspecialchars($b['branch_code']) ?>">
                <?= htmlspecialchars($b['branch'] ?? $b['branch_code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Brand</label>
          <select id="selectBrandFlaggedEmployees" class="form-select">
            <option value="ALL">All Brands</option>
            <?php foreach ($brands as $brandName): ?>
              <option value="<?= htmlspecialchars($brandName) ?>"><?= htmlspecialchars($brandName) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Period (Period Since Oldest Flag)</label>
          <select id="selectPeriodFlaggedEmployees" class="form-select">
            <option value="all">All</option>
            <option value="lt15">Less than 15 Days</option>
            <option value="15_30">15 - 30 Days</option>
            <option value="1_2mo">1 - 2 Months</option>
            <option value="2mo_plus">More than 2 Months</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="btnGenerateFlaggedEmployees" class="btn btn-primary"
                onclick="generateReport('flagged_employees')">
          Generate Report
        </button>
      </div>
    </div>
  </div>
</div>