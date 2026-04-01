<div class="modal fade" id="addEmployeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <form id="addEmployeeForm">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div id="employeeAlert"></div> <!-- Alert container -->

                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" id="firstName" class="form-control" required style="text-transform: uppercase;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="lastName" class="form-control" required style="text-transform: uppercase;">
                    </div>

                    <script>
                    document.getElementById('firstName').addEventListener('input', function() {
                        this.value = this.value.toUpperCase();
                    });
                    document.getElementById('lastName').addEventListener('input', function() {
                        this.value = this.value.toUpperCase();
                    });
                    </script>

                    <?php
                    $pdo = qa_db();

                    // Fetch distinct branches
                    $branches = $pdo->query("
                        SELECT DISTINCT branch_name 
                        FROM assignment
                        WHERE assigned_count < required_count
                        ORDER BY branch_name
                    ")->fetchAll(PDO::FETCH_COLUMN);

                    $brands = $pdo->query("
                        SELECT DISTINCT brand_name 
                        FROM assignment
                        WHERE assigned_count < required_count
                        ORDER BY brand_name
                    ")->fetchAll(PDO::FETCH_COLUMN);
                    ?>

                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select name="branch" class="form-select">
                            <option value="" selected disabled>Unassigned</option>
                            <?php foreach($branches as $branch): ?>
                                <option value="<?= htmlspecialchars($branch) ?>"><?= htmlspecialchars($branch) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Brand</label>
                        <select name="brand" class="form-select">
                            <option value="" selected disabled>Unassigned</option>
                            <?php foreach($brands as $brand): ?>
                                <option value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="status" id="employeeStatus" value="">

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- Use CDN for reliability -->
<script src="sweetalert/dist/sweetalert2.all.min.js"></script>

<script src="assets/js/add_employee.js"></script>
