<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$allowed = ['admin', 'super_admin'];
if (!in_array($_SESSION['role'] ?? '', $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

try {
    $pdo  = qa_db();
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        UPDATE users
           SET password   = :password,
               updated_at = GETDATE(),
               first_login = 1
         WHERE username   = :username
    ");

    $stmt->execute([
        ':password' => $hash,
        ':username' => $username,
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}