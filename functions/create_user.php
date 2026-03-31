<?php
session_start();
include '../config/db.php';

// 🔒 Only admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$pdo = qa_db();

// Get inputs
$username = strtoupper(trim($_POST['username']));
$role     = $_POST['role'];
$branch   = $_POST['branch'] ?: null;
$brand    = $_POST['brand'] ?: null;

// ✅ DEFAULT PASSWORD
$defaultPassword = 'Password123';
$hashedPassword  = password_hash($defaultPassword, PASSWORD_DEFAULT);

/* =========================
   ✅ DUPLICATE CHECK HERE
========================= */
$check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$check->execute([$username]);

if ($check->fetchColumn() > 0) {
    header("Location: ../users.php?error=exists");
    exit;
}

/* =========================
   INSERT USER
========================= */
$stmt = $pdo->prepare("
    INSERT INTO users (username, password, role, branch, brand)
    VALUES (:username, :password, :role, :branch, :brand)
");

$stmt->execute([
    ':username' => $username,
    ':password' => $hashedPassword,
    ':role'     => $role,
    ':branch'   => $branch,
    ':brand'    => $brand
]);

header("Location: ../users.php?success=1");
exit;