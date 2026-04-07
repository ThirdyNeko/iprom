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

            // 🔥 Regenerate session ID (VERY IMPORTANT)
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['branch'] = $user['branch'] ?? null;
            $_SESSION['brand']  = $user['brand'] ?? null;

            header("Location: ../index.php");
            exit;

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
<title>Login - Promodizer Manager</title>

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/bootstrap-icons/font/bootstrap-icons.min.css">
<script src ="http://192.168.40.14/branch_logger/hooks/qa_hook.js"></script>

<style>
body {
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: #343a40;
}

.login-card {
    width: 100%;
    max-width: 400px;
    background-color: #495057;
    padding: 2rem;
    border-radius: 0.5rem;
    color: #fff;
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.3);
}

.form-control {
    background-color: #2a2a2a;
    border: 1px solid #444;
    color: #fff;
}

.form-control::placeholder {
    color: #aaa;
}

.input-group-text.toggle-password {
    background-color: #2a2a2a;
    border: 1px solid #444;
    color: #aaa;
    cursor: pointer;
    transition: color 0.2s, background-color 0.2s;
}

.input-group-text.toggle-password:hover {
    color: #fff;
    background-color: #333;
}

.btn-primary {
    width: 100%;
}
</style>
</head>
<body>

<div class="login-card">
    <h3 class="text-center mb-4">Promodizer Manager</h3>

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
                <i class="bi bi-eye"></i>
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
    icon.classList.toggle('bi-eye');
    icon.classList.toggle('bi-eye-slash');
});
</script>

</body>
</html>