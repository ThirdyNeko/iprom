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
        background-color: #fffbdf;
    }
    .modal .form-control[readonly] {
        background-color: #e9ecef;
        opacity: 1;
    }
    .modal .form-control.text-uppercase {
        text-transform: uppercase;
    }
    .modal .form-select {
        background-color: #fffbdf !important;
        opacity: 1;
    }
    .modal .form-control[disabled],
    .modal .form-select[disabled] {
        background-color: #e9ecef !important;
        opacity: 1;
        cursor: not-allowed;
    }

    /* ── two-pane branch layout ── */
    #branchSelect {
        display: flex;
        height: 260px;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        overflow: hidden;
        background: #fff;
    }

    .branch-col {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .branch-col-header {
        padding: 4px 8px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #6c757d;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        flex-shrink: 0;
    }

    .branch-pane {
        flex: 1;
        overflow-y: auto;
        padding: 4px;
    }

    .branch-col-divider {
        width: 1px;
        background: #dee2e6;
        flex-shrink: 0;
    }

    .branch-item {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 1px 2px;
        box-sizing: border-box;
    }
</style>

<div class="modal fade" id="createUserModal" tabindex="-1">

    <div class="modal-dialog modal-xl">

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

                                    <option value="" disabled selected>
                                        Select Role
                                    </option>

                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
                                    <option value="admin">ADMIN</option>
                                    <?php endif; ?>

                                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin') || ($_SESSION['role'] === 'super_admin')): ?>
                                    <option value="supervisor">SUPERVISOR</option>
                                    <?php endif; ?>

                                    <option value="staff">STAFF</option>

                                </select>

                            </div>

                            <!-- BRANCH -->
                            <div class="mb-3">

                                <label class="form-label mb-0">
                                    Branches
                                </label>

                                <small id="branchCounter" class="text-muted">
                                    Selected: 0
                                </small>

                                <!-- Search -->
                                <input type="text"
                                       id="branchSearch"
                                       class="form-control mb-2"
                                       placeholder="Search branches..."
                                       style="text-transform: uppercase;"
                                       disabled>

                                <!-- Branch List — JS will split this into left/right panes -->
                                <div id="branchSelect">

                                    <?php foreach ($branches as $b): ?>
                                        <div class="branch-item"
                                             style="margin: 2px 0;">

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

                        </div>
                        <div class="col-md-6">

                            <div class="mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text"
                                       id="first_name"
                                       name="first_name"
                                       class="form-control text-uppercase"
                                       style="text-transform: uppercase;"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Last Name</label>
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
                                <label class="form-label">Position</label>
                                <input type="text"
                                       name="position"
                                       class="form-control"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Default Password</label>
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

                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i>
                        Create
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/users/create_user.js"></script>
<script src="assets/js/users/roles.js"></script>
<script src="assets/js/users/username.js"></script>