<?php
session_start();
include '../config/db.php';

$error = '';
$pdo = qa_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number']);
    $password  = trim($_POST['password']);

    if ($id_number === '' || $password === '') {
        $error = "Please enter both ID and password.";
    } else {
        // Fetch user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :id_number");
        $stmt->execute([':id_number' => $id_number]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Login success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['branch'] = $user['branch'];
            $_SESSION['brand'] = $user['brand'];

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
            <label class="form-label">Admin ID Number</label>
            <input type="text"
                   name="id_number"
                   class="form-control form-control-lg text-center"
                   placeholder="Enter Admin ID"
                   required>
        </div>

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