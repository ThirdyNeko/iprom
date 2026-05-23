<?php
session_start();
include '../config/db.php';

$error = '';
$pdo = qa_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password  = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Please enter both username and password.";
    } else {

        $stmt = $pdo->prepare("
            EXEC get_user_by_username @username = :username
        ");

        $stmt->execute([
            ':username' => $username
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 🔐 Always check user first
        if ($user && password_verify($password, $user['password'])) {

            // ❌ Prevent inactive users from logging in
            if (strtoupper(trim($user['status'] ?? '')) === 'INACTIVE') {
                $error = "Your account is inactive. Please contact the administrator.";
            } else {

                // 🔥 Regenerate session ID (VERY IMPORTANT)
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch'] = $user['branch'] ?? null;
                $_SESSION['brand']  = $user['brand'] ?? null;
                $_SESSION['position'] = $user['position'] ?? null;
                $_SESSION['department'] = $user['department'] ?? null;
                $_SESSION['status'] = $user['status'] ?? null;
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
    background: #f1f5f9; /* light background like dashboard */
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
.form-control {
    background: #f9fafb;
    border: 1px solid #d1d5db;
    color: #111827;
    border-radius: 10px;
    transition: all 0.2s ease;
}

/* INPUT FOCUS */
.form-control:focus {
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
</style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <img src="../assets/icons/LOGO ONLY RED.png" alt="iProm Logo" class="login-logo mb-2">
        <h3 class="m-0">iProm</h3>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger text-center small"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-4">
            <input type="text"
                name="username"
                id="username"
                class="form-control form-control-lg text-center uppercase-input"
                placeholder="Enter Username"
                required>
        </div>

        <style>
        /* Only transform the typed text, not the placeholder */
        .uppercase-input {
            text-transform: none; /* base input text will be controlled by JS */
        }

        .uppercase-input::-ms-input-placeholder { /* IE 10+ */
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
</script>

</body>
</html>