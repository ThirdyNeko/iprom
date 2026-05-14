<?php

$branches = [];
$brands   = [];

try {

    $stmt = $pdo->prepare("
        EXEC dbo.get_branches_brands @branch = NULL
    ");

    $stmt->execute();

    // FIRST RESULT SET = BRANCHES
    $branches = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // MOVE TO SECOND RESULT SET
    $stmt->nextRowset();

    // SECOND RESULT SET = BRANDS
    $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {

    $branches = [];
    $brands   = [];

}

?>

<div class="modal fade" id="createUserModal" tabindex="-1">

    <div class="modal-dialog">

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

                    <!-- USERNAME -->
                    <div class="mb-3">

                        <label class="form-label">
                            Username
                        </label>

                        <input type="text"
                               name="username"
                               class="form-control text-uppercase"
                               style="text-transform: uppercase;"
                               required>

                    </div>

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

                            <option value="admin">
                                ADMIN
                            </option>

                            <option value="hr">
                                HUMAN RESOURCES
                            </option>

                            <option value="manager">
                                MANAGER
                            </option>

                        </select>

                    </div>

                    <!-- BRANCH -->
                    <div class="mb-3">

                        <label class="form-label">
                            Branch
                            <small class="text-muted">(Optional)</small>
                        </label>

                        <select name="branch"
                                id="branchSelect"
                                class="form-select">

                            <option value="">
                                NONE
                            </option>

                            <?php foreach ($branches as $branch): ?>

                                <option value="<?= htmlspecialchars($branch) ?>">

                                    <?= htmlspecialchars($branch) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- BRAND -->
                    <div class="mb-3">

                        <label class="form-label">
                            Brand
                            <small class="text-muted">(Optional)</small>
                        </label>

                        <select name="brand"
                                id="brandSelect"
                                class="form-select">

                            <option value="">
                                NONE
                            </option>

                            <?php foreach ($brands as $brand): ?>

                                <option value="<?= htmlspecialchars($brand) ?>">

                                    <?= htmlspecialchars($brand) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

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