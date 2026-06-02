<?php

$branches = [];
$brands   = [];

try {

    $stmt = $pdo->prepare("
        EXEC dbo.get_branches_brands @branch = NULL
    ");

    $stmt->execute();

    // FIRST RESULT SET = BRANCHES
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // MOVE TO SECOND RESULT SET
    $stmt->nextRowset();

    // SECOND RESULT SET = BRANDS
    $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {

    $branches = [];
    $brands   = [];

}
?>

<style>
    .modal .form-label {
        font-weight: 600;
    }

    .modal .form-control,
    .modal .form-select {
        border-radius: 0.25rem;
        background-color: #fffbdf; /* default to editable style */
    }
    .modal .form-control[readonly] {
        background-color: #e9ecef;
        opacity: 1;
    }
    .modal .form-control.text-uppercase {
        text-transform: uppercase;
    }
    .modal .form-select {
        background-color: #fffbdf !important; /* editable */
        opacity: 1;
    }
    .modal .form-control[disabled],
    .modal .form-select[disabled] {
        background-color: #e9ecef !important; /* readonly/disabled */
        opacity: 1;
        cursor: not-allowed;
    }
</style>

<div class="modal fade" id="createUserModal" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form action="functions/create_user.php" method="POST">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Create User
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <!-- ROLE -->
                            <div class="mb-3">

                                <label class="form-label">
                                    Role
                                </label>

                                <select name="role"
                                        class="form-select"
                                        required>

                                    <option value=""
                                            disabled
                                            selected>

                                        Select Role

                                    </option>
                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
                                    <option value="admin">
                                        ADMIN
                                    </option>

                                    <!-- <option value="inhouse_manager">
                                        INHOUSE MANAGER
                                    </option>

                                    <option value="branch_manager">
                                        BRANCH MANAGER
                                    </option> -->
                                    <?php endif; ?>

                                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin') || ($_SESSION['role'] === 'super_admin')): ?>
                                    <option value="supervisor">
                                        SUPERVISOR
                                    </option>
                                    <?php endif; ?>

                                    <option value="staff">
                                        STAFF
                                    </option>

                                    

                                </select>

                            </div>

                            <!-- BRANCH -->
                            <div class="mb-3">

                                <label class="form-label">
                                    Branches
                                </label>

                                <div id="branchSelect" 
                                    class="form-control branch-checkbox-group" 
                                    style="height: 120px; overflow-y: auto; padding: 4px;">

                                    <?php foreach ($branches as $b): ?>
                                        <div class="form-check" style="margin: 2px 4px;">
                                            <input 
                                                class="form-check-input" 
                                                type="checkbox" 
                                                name="branches[]" 
                                                id="branch_<?= htmlspecialchars($b['branch_code']) ?>"
                                                value="<?= htmlspecialchars($b['branch_code']) ?>"
                                                disabled>
                                            <label 
                                                class="form-check-label" 
                                                for="branch_<?= htmlspecialchars($b['branch_code']) ?>">
                                                <?= htmlspecialchars($b['branch']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>

                                </div>

                            </div>

                            <!-- BRAND -->
                            <!-- <div class="mb-3">

                                <label class="form-label">
                                    Brand
                                    <small class="text-muted">(Optional)</small>
                                </label>

                                <select name="brand"
                                        id="brandSelect"
                                        class="form-select"
                                        disabled>

                                    <option value="">
                                        NONE
                                    </option>

                                    <?php foreach ($brands as $brand): ?>

                                        <option value="<?= htmlspecialchars($brand) ?>">

                                            <?= htmlspecialchars($brand) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div> -->

                            <div class="mb-3">
                                <label class = "form-label">
                                    Department
                                </label>
                                <input type="text"
                                    id="departmentInput"
                                    name="department"
                                    class="form-control text-uppercase"
                                    style="text-transform: uppercase;"
                                    disabled>
                            </div>

                        </div>
                        <div class="col-md-6">

                            <div class="mb-3">
                                <label class = "form-label">
                                    First Name
                                </label>
                                <input type="text"
                                    id="first_name"
                                    name="first_name"
                                    class="form-control text-uppercase"
                                    style="text-transform: uppercase;"
                                    required>
                            </div>

                            <div class="mb-3">
                                <label class = "form-label">
                                    Last Name
                                </label>
                                <input type="text"
                                    id="last_name"
                                    name="last_name"
                                    class="form-control text-uppercase"
                                    style="text-transform: uppercase;"
                                    required>
                            </div>                 

                            <!-- USERNAME -->
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text"
                                    id="username"
                                    name="username"
                                    class="form-control text-uppercase"
                                    readonly>
                            </div>     
                            
                            <div class="mb-3">
                                <label class = "form-label">
                                    Position
                                </label>
                                <input type="text"
                                    name="position"
                                    class="form-control"
                                    required>
                            </div>   

                            <div class="mb-3">
                                <label class = "form-label">
                                    Default Password
                                </label>
                                <input type="text"
                                    name="default_password"
                                    class="form-control"
                                    value="Password123"
                                    readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">

                    <button type="submit"
                            class="btn btn-success">

                        <i class="bi bi-check-circle"></i>
                        Create

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script src = "sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/users/create_user.js"></script>
<script src="assets/js/users/roles.js"></script>
<script src="assets/js/users/username.js"></script>
