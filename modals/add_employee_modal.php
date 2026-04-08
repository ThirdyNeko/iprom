<?php
$pdo = qa_db();

// Fetch all branches (no filtering)
$branches = $pdo->query("
    SELECT DISTINCT branch_name
    FROM assignment
    WHERE branch_name IS NOT NULL
    ORDER BY branch_name
")->fetchAll(PDO::FETCH_COLUMN);

// Fetch all brands (no filtering)
$brands = $pdo->query("
    SELECT DISTINCT brand_name
    FROM assignment
    WHERE brand_name IS NOT NULL
    ORDER BY brand_name
")->fetchAll(PDO::FETCH_COLUMN);

// Fetch branch-brand pairs for JS mapping
$branch_brand_pairs = $pdo->query("
    SELECT branch_name, brand_name, assigned_count, required_count
    FROM assignment
    WHERE branch_name IS NOT NULL AND brand_name IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ADD EMPLOYEE MODAL -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="addEmployeeForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="employeeAlert"></div>

                    <div class="row g-3">
                        <!-- LEFT COLUMN -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Branch</label>
                                <select name="branch" id="mainBranch" class="form-select" required>
                                    <option value="" selected disabled>Unassigned</option>
                                    <?php foreach($branches as $branch): ?>
                                        <option value="<?= htmlspecialchars($branch) ?>"><?= htmlspecialchars($branch) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required style="text-transform: uppercase;">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Date Hired</label>
                                <input type="date" name="date_hired" class="form-control" required>
                            </div>

                            <div id="dateRangeFields" class="d-none">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Brand</label>
                                <select name="brand" id="mainBrand" class="form-select" required>
                                    <option value="" selected disabled>Unassigned</option>
                                    <?php foreach($brands as $brand): ?>
                                        <option value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required style="text-transform: uppercase;">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Employment Status</label>
                                <select name="employment_status" id="employmentStatus" class="form-select" required>
                                    <option value="" disabled selected>Select Status</option>
                                    <option value="PERMANENT">Permanent</option>
                                    <option value="SEASONAL">Seasonal</option>
                                    <option value="RELIEVER">Reliever</option>
                                    <option value="ROVING">Roving</option>
                                </select>
                            </div>

                            <!-- ROVING BRANCHES -->
                            <div id="rovingField" class="mb-3 d-none">
                                <label class="form-label">Roving Branches</label>
                                <div id="rovingContainer">
                                    <div class="input-group mb-2 roving-row">
                                        <select name="roving_branches[]" class="form-select roving-select" required>
                                            <option value="" disabled selected>Select Branch</option>
                                            <?php foreach($branches as $branch): ?>
                                                <option value="<?= htmlspecialchars($branch) ?>"><?= htmlspecialchars($branch) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-success add-branch">+</button>
                                        <button type="button" class="btn btn-danger remove-branch">−</button>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Remarks <small class="text-muted">(100 characters max)</small></label>
                                <textarea name="remarks" class="form-control" maxlength="100" rows="3"></textarea>
                                <div class="text-end"><small id="remarksCount">0 / 100</small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Employee</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JS -->
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script>
    window.branchBrandMapping = <?= json_encode($branch_brand_pairs) ?>;
</script>
<script src="assets/js/add_employee.js"></script>