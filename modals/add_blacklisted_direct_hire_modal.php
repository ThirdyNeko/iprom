<?php
try {
    $stmt = $pdo->prepare("EXEC dbo.get_suffixes");
    $stmt->execute();
    $bldh_suffixes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bldh_suffixes = [];
}
?>

<!-- Add Blacklisted (Direct Hire) Modal -->
<style>
#addBlacklistedDirectHireModal .form-control:not([readonly]):not([disabled]),
#addBlacklistedDirectHireModal .form-select:not([disabled]) {
    background-color: #fffbdf !important; /* editable */
    opacity: 1;
}
#addBlacklistedDirectHireModal .form-control[readonly],
#addBlacklistedDirectHireModal .form-control[disabled],
#addBlacklistedDirectHireModal .form-select[disabled] {
    background-color: #e9ecef !important; /* readonly / disabled */
    opacity: 1;
    cursor: not-allowed;
}
#addBlacklistedDirectHireModal .form-control:focus,
#addBlacklistedDirectHireModal .form-select:focus {
    box-shadow: 0 0 0 0.15rem rgba(255, 193, 7, 0.25);
    border-color: #ffc107;
}
</style>

<div class="modal fade" id="addBlacklistedDirectHireModal" tabindex="-1" aria-labelledby="addBlacklistedDirectHireModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="addBlacklistedDirectHireModalLabel">Add Blacklisted Person (Direct Hire)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addBlacklistedDirectHireForm" novalidate>

          <div class="row g-3">

            <div class="col-md-4">
              <label class="form-label">First Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="bldh_first_name" style="text-transform: uppercase;" required>
            </div>
            <div class="col-md-4">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0">Middle Name <span class="text-danger">*</span></label>
                <div class="form-check mb-0">
                  <input class="form-check-input" type="checkbox" id="bldh_no_middle_name">
                  <label class="form-check-label mb-0" for="bldh_no_middle_name">
                    No Middle Name
                  </label>
                </div>
              </div>
              <input type="text" class="form-control" id="bldh_middle_name" style="text-transform: uppercase;" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="bldh_last_name" style="text-transform: uppercase;" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Suffix</label>
              <select class="form-select" id="bldh_suffix">
                <option value="">NONE</option>
                <?php foreach ($bldh_suffixes as $s): ?>
                    <option value="<?= htmlspecialchars($s['suffix']) ?>"><?= htmlspecialchars($s['suffix']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Gender <span class="text-danger">*</span></label>
              <select class="form-select" id="bldh_gender" required>
                <option value="" selected disabled>Select Gender</option>
                <option value="MALE">Male</option>
                <option value="FEMALE">Female</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Birthdate <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="bldh_birthdate" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Marital Status <span class="text-danger">*</span></label>
              <select class="form-select" id="bldh_marital_status" required>
                <option value="" selected disabled>Select Marital Status</option>
                <option value="SINGLE">Single</option>
                <option value="MARRIED">Married</option>
                <option value="WIDOWED">Widowed</option>
                <option value="SEPARATED">Separated</option>
                <option value="DIVORCED">Divorced</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Branch <span class="text-danger">*</span></label>
              <select class="form-select" id="bldh_branch" required>
                <option value="" selected disabled>Select Branch</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">End Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="bldh_end_date" required>
            </div>

            <div class="col-12">
              <label class="form-label">Remarks / Violation <span class="text-danger">*</span></label>
              <textarea class="form-control" id="bldh_remarks" rows="3" maxlength="100" required></textarea>
              <div class="d-flex justify-content-end">
                <small class="text-muted"><span id="bldh_remarks_count">0</span>/100</small>
              </div>
            </div>

          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveBlacklistedDirectHireBtn">Save</button>
      </div>
    </div>
  </div>
</div>