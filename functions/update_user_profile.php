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
$position = trim($_POST['position'] ?? '');
$role     = trim($_POST['role'] ?? '');

$validRoles = ['staff', 'supervisor', 'admin', 'super_admin'];

if (!$username || !$position || !in_array($role, $validRoles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

try {
    $pdo = qa_db();
    $stmt = $pdo->prepare("UPDATE users SET position = :position, role = :role, updated_at = GETDATE() WHERE username = :username");
    $stmt->execute([
        ':position' => $position,
        ':role'     => $role,
        ':username' => $username,
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}