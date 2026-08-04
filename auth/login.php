<?php
session_start();
include '../config/db.php';

$error = '';
$pdo = qa_db();

// fetch branches for the dropdown
$branches = [];
try {
    $branchStmt = $pdo->query("
        SELECT branch, branch_code
        FROM dbo.branches
        ORDER BY branch
    ");
    $branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    file_put_contents(__DIR__ . '/../error_log.txt', "[" . date("Y-m-d H:i:s") . "] Branch fetch error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
}

// preserve the selected branch across a failed submit
$branchSelect = trim($_POST['branch_select'] ?? '');
$branchLabel = '';
if ($branchSelect === 'HEAD_OFFICE') {
    $branchLabel = 'Head Office';
} elseif ($branchSelect !== '') {
    foreach ($branches as $b) {
        if ($b['branch_code'] === $branchSelect) {
            $branchLabel = $b['branch'];
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '' || $branchSelect === '') {
        $error = "Please select a branch and enter both username and password.";
    } else {

        if ($branchSelect === 'HEAD_OFFICE') {
            $stmt = $pdo->prepare("
                SELECT * FROM users
                WHERE username = :username
                  AND role IN ('staff', 'admin', 'super_admin', 'supervisor')
                  AND UPPER(LTRIM(RTRIM(status))) = 'ACTIVE'
            ");
            $stmt->execute([
                ':username' => $username
            ]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM users
                WHERE username = :username
                  AND role = 'branch_manager'
                  AND branch = :branch
                  AND UPPER(LTRIM(RTRIM(status))) = 'ACTIVE'
            ");
            $stmt->execute([
                ':username' => $username,
                ':branch'   => $branchSelect
            ]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 🔐 Match against ALL rows for this username/branch — not just the first one
        // returned. Two employees can legitimately share a name+branch, and each
        // must only ever match their own password, never whichever row the query
        // happened to return first (no ORDER BY = non-deterministic on ties).
        $user = null;
        foreach ($rows as $row) {
            if (password_verify($password, $row['password'])) {
                $user = $row;
                break;
            }
        }

        if ($user) {

            // 🚧 Check maintenance mode
            $maintenanceFile = __DIR__ . '/../maintenance.flag';
            $allowedUsernames = ['QA_HR_ADMIN', 'QA_HR_SUPERVISOR', 'QA_HR_STAFF'];
            $blockedByMaintenance = false;
            $maintenanceMessage = 'The system is currently under maintenance. Please try again later.';

            if (file_exists($maintenanceFile)) {
                $flagData = json_decode(file_get_contents($maintenanceFile), true);
                if (!empty($flagData['message'])) {
                    $maintenanceMessage = $flagData['message'];
                }

                if ($user['role'] !== 'super_admin' && !in_array($user['username'], $allowedUsernames)) {
                    $blockedByMaintenance = true;
                }
            }

            if ($blockedByMaintenance) {
                $error = $maintenanceMessage;
            } else {

                // 🔥 Regenerate session ID (VERY IMPORTANT)
                session_regenerate_id(true);

                $_SESSION['user_id']     = $user['id'];
                $_SESSION['username']    = $user['username'];
                $_SESSION['role']        = $user['role'];
                $_SESSION['branch']      = $user['branch'] ?? null;
                $_SESSION['brand']       = $user['brand'] ?? null;
                $_SESSION['position']    = $user['position'] ?? null;
                $_SESSION['department']  = $user['department'] ?? null;
                $_SESSION['status']      = $user['status'] ?? null;
                $_SESSION['first_login'] = $user['first_login'] ?? null;

                header("Location: ../index.php");
                exit;
            }

        } else {
            $error = "Invalid ID or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../assets/icons/LOGO ONLY RED.png">
<title>iProm</title>

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/bootstrap-icons/font/bootstrap-icons.min.css">
<script src ="http://localhost/branch_logger/hooks/qa_hook.js"></script>

<style>
body {
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f1f5f9;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

/* CARD */
.login-card {
    width: 100%;
    max-width: 400px;
    background: #ffffff;
    padding: 2.2rem;
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

/* LOGO */
.login-logo {
    width: 60px;
    height: 60px;
    object-fit: contain;
}

/* TITLE */
.login-card h3 {
    color: #1e3a8a;
    font-weight: 600;
}

/* INPUT */
.form-control, .form-select {
    background: #f9fafb;
    border: 1px solid #d1d5db;
    color: #111827;
    border-radius: 10px;
    transition: all 0.2s ease;
}

/* INPUT FOCUS */
.form-control:focus, .form-select:focus {
    background: #fff;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

/* PLACEHOLDER */
.form-control::placeholder {
    color: #9ca3af;
}

/* PASSWORD TOGGLE */
.input-group-text.toggle-password {
    background: #f9fafb;
    border: 1px solid #d1d5db;
    color: #6b7280;
    cursor: pointer;
    border-radius: 0 10px 10px 0;
    transition: all 0.2s ease;
}

.input-group-text.toggle-password:hover {
    background: #eef2ff;
    color: #2563eb;
}

/* BUTTON */
.btn-primary {
    width: 100%;
    border-radius: 10px;
    background: #2563eb;
    border: none;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background: #1d4ed8;
}

/* ERROR ALERT */
.alert-danger {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 8px;
}

/* MAINTENANCE ALERT */
.alert-warning {
    background: #fef9c3;
    border: 1px solid #fde047;
    color: #854d0e;
    border-radius: 8px;
}

/* CUSTOM BRANCH DROPDOWN */
.dropdown-toggle-custom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
}
.dropdown-toggle-custom::after {
    margin-left: auto;
}

.branch-dropdown-menu {
    max-height: 280px;
    overflow-y: auto;
    padding: 0.25rem 0;
}

.branch-dropdown-menu .dropdown-item {
    padding: 0.5rem 1rem;
    cursor: pointer;
}

.branch-dropdown-menu .dropdown-item:active,
.branch-dropdown-menu .dropdown-item.active {
    background-color: #2563eb;
    color: #fff;
}
</style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <img src="../assets/icons/LOGO ONLY RED.png" alt="iProm Logo" class="login-logo mb-2">
        <h3 class="m-0">iProm</h3>
    </div>

    <?php if ($error): ?>
        <?php $isMaintenance = str_contains($error, 'maintenance'); ?>
        <div class="alert <?= $isMaintenance ? 'alert-warning' : 'alert-danger' ?> text-center small">
            <?php if ($isMaintenance): ?>
                <i class="bi bi-cone-striped me-1"></i>
            <?php endif; ?>
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
        <div class="mb-4">
            <div class="dropdown">
                <button
                    class="form-select form-select-lg text-start dropdown-toggle-custom"
                    type="button"
                    id="branchDropdownBtn"
                    data-bs-toggle="dropdown"
                    data-bs-display="static"
                    aria-expanded="false">
                    <span id="branchDropdownLabel" class="<?= $branchSelect === '' ? 'text-muted' : '' ?>">
                        <?= $branchSelect !== '' ? htmlspecialchars($branchLabel) : 'Select Branch' ?>
                    </span>
                    <i class="bi bi-chevron-down text-secondary"></i>
                </button>
                <ul class="dropdown-menu w-100 branch-dropdown-menu" aria-labelledby="branchDropdownBtn">
                    <li class="px-2 pb-2">
                        <input type="text" id="branchSearchInput" class="form-control form-control-sm" placeholder="Search branch...">
                    </li>
                    <li><a class="dropdown-item branch-option <?= $branchSelect === 'HEAD_OFFICE' ? 'active' : '' ?>" href="#" data-value="HEAD_OFFICE">HEAD OFFICE</a></li>
                    <?php foreach ($branches as $b): ?>
                        <li>
                            <a class="dropdown-item branch-option <?= $branchSelect === $b['branch_code'] ? 'active' : '' ?>"
                               href="#"
                               data-value="<?= htmlspecialchars($b['branch_code']) ?>">
                                <?= htmlspecialchars($b['branch']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <input type="hidden" name="branch_select" id="branch_select" value="<?= htmlspecialchars($branchSelect) ?>">
            </div>
        </div>

        <div class="mb-4">
            <input type="text"
                name="username"
                id="username"
                class="form-control form-control-lg text-center uppercase-input"
                placeholder="Enter Username"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                required>
        </div>

        <style>
        .uppercase-input {
            text-transform: none;
        }
        .uppercase-input::-ms-input-placeholder {
            text-transform: none;
        }
        .uppercase-input::placeholder {
            text-transform: none;
        }
        </style>

        <script>
        document.getElementById('username').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        </script>

        <div class="input-group mb-4">
            <input type="password"
                   id="password"
                   name="password"
                   class="form-control form-control-lg text-center"
                   placeholder="Enter Password"
                   required>
            <span class="input-group-text toggle-password" id="togglePassword">
                <i class="bi bi-eye-slash"></i>
            </span>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100">
            Login
        </button>
    </form>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');
const icon = togglePassword.querySelector('i');

togglePassword.addEventListener('click', () => {
    const type = passwordInput.type === 'password' ? 'text' : 'password';
    passwordInput.type = type;
    icon.classList.toggle('bi-eye-slash');
    icon.classList.toggle('bi-eye');
});

// Force the branch dropdown to always open downward, never flip above
const branchDropdownEl = document.getElementById('branchDropdownBtn');
const branchDropdown = new bootstrap.Dropdown(branchDropdownEl, {
    popperConfig: (defaultConfig) => ({
        ...defaultConfig,
        modifiers: [
            ...defaultConfig.modifiers.filter(m => m.name !== 'flip'),
            { name: 'flip', enabled: false }
        ]
    })
});

document.querySelectorAll('.branch-option').forEach(option => {
    option.addEventListener('click', function (e) {
        e.preventDefault();

        document.querySelectorAll('.branch-option').forEach(o => o.classList.remove('active'));
        this.classList.add('active');

        const value = this.dataset.value;
        const label = this.textContent.trim();

        document.getElementById('branch_select').value = value;
        const labelEl = document.getElementById('branchDropdownLabel');
        labelEl.textContent = label;
        labelEl.classList.remove('text-muted');

        branchDropdown.hide();
    });
});

// filter branch options as the user types
const branchSearchInput = document.getElementById('branchSearchInput');
branchSearchInput.addEventListener('input', function () {
    const term = this.value.trim().toLowerCase();
    document.querySelectorAll('.branch-option').forEach(option => {
        const li = option.closest('li');
        const matches = option.textContent.toLowerCase().includes(term);
        li.style.display = matches ? '' : 'none';
    });
});

// keep the search box open and usable — stop clicks inside it from closing the dropdown
branchSearchInput.addEventListener('click', function (e) {
    e.stopPropagation();
});

// autofocus the search box when the dropdown opens, and reset filter/focus on close
branchDropdownEl.addEventListener('shown.bs.dropdown', function () {
    branchSearchInput.value = '';
    document.querySelectorAll('.branch-option').forEach(option => {
        option.closest('li').style.display = '';
    });
    branchSearchInput.focus();
});

// Guard: hidden input can't use native "required" validation UI,
// so check on submit and block if no branch was picked
document.getElementById('loginForm').addEventListener('submit', function (e) {
    const branchVal = document.getElementById('branch_select').value;
    if (!branchVal) {
        e.preventDefault();
        alert('Please select a branch.');
    }
});
</script>

</body>
</html>