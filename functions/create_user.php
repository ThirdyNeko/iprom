<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$pdo = qa_db();

// Inputs
$username   = isset($_POST['username']) ? strtoupper(trim($_POST['username'])) : null;
$role       = $_POST['role'] ?? null;

$branch     = !empty($_POST['branch']) ? $_POST['branch'] : null;
$brand      = !empty($_POST['brand']) ? $_POST['brand'] : null;

$first_name = !empty($_POST['first_name']) ? strtoupper(trim($_POST['first_name'])) : null;
$last_name  = !empty($_POST['last_name']) ? strtoupper(trim($_POST['last_name'])) : null;
$position   = !empty($_POST['position']) ? strtoupper(trim($_POST['position'])) : null;
$department = !empty($_POST['department']) ? strtoupper(trim($_POST['department'])) : null;

// Validation
if (!$username || !$role || !$first_name || !$last_name || !$position) {
    header("Location: ../users.php?error=invalid");
    exit;
}

// Default password
$defaultPassword = 'Password123';
$hashedPassword  = password_hash($defaultPassword, PASSWORD_DEFAULT);

// Duplicate check
$check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$check->execute([$username]);

if ($check->fetchColumn() > 0) {
    header("Location: ../users.php?error=exists");
    exit;
}

// INSERT (UPDATED TABLE STRUCTURE)
$stmt = $pdo->prepare("
    INSERT INTO users (
        username,
        password,
        role,
        branch,
        brand,
        first_name,
        last_name,
        position,
        department
    )
    VALUES (
        :username,
        :password,
        :role,
        :branch,
        :brand,
        :first_name,
        :last_name,
        :position,
        :department
    )
");

$stmt->execute([
    ':username'   => $username,
    ':password'   => $hashedPassword,
    ':role'       => $role,
    ':branch'     => $branch,
    ':brand'      => $brand,
    ':first_name' => $first_name,
    ':last_name'  => $last_name,
    ':position'   => $position,
    ':department' => $department
]);

header("Location: ../users.php?success=1");
exit;