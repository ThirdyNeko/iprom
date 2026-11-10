<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$allowed = ['admin', 'super_admin', 'audit_manager'];
if (!in_array($_SESSION['role'] ?? '', $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$id         = trim($_POST['id'] ?? '');
$position   = trim($_POST['position'] ?? '');
$role       = trim($_POST['role'] ?? '');
$firstName  = strtoupper(trim($_POST['first_name'] ?? ''));
$middleName = strtoupper(trim($_POST['middle_name'] ?? ''));
$lastName   = strtoupper(trim($_POST['last_name'] ?? ''));
$suffix     = strtoupper(trim($_POST['suffix'] ?? ''));

$validRoles = ['staff', 'supervisor', 'branch_manager', 'admin', 'super_admin', 'audit_manager', 'audit_supervisor', 'audit_staff', 'regional_manager'];

if (!$id || !$position || !$firstName || !$lastName || !in_array($role, $validRoles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

// Username is always derived server-side, never trusted from the client.
// Format: "FIRST LAST SUFFIX" uppercase (suffix optional).
$username = strtoupper(implode(' ', array_filter(
    [$firstName, $lastName, $suffix],
    fn($v) => $v !== ''
)));

$defaultPassword = 'Password123';

$hashedPassword = password_hash(
    $defaultPassword,
    PASSWORD_DEFAULT
);

try {
    $pdo = qa_db();
    $stmt = $pdo->prepare("
        UPDATE users
        SET position    = :position,
            role        = :role,
            first_name  = :first_name,
            middle_name = :middle_name,
            last_name   = :last_name,
            suffix      = :suffix,
            username    = :username,
            updated_at  = GETDATE(),
            password    = :password,
            first_login  = 1
        WHERE id = :id
    ");
    $stmt->execute([
        ':position'    => $position,
        ':role'        => $role,
        ':first_name'  => $firstName,
        ':middle_name' => $middleName,
        ':last_name'   => $lastName,
        ':suffix'      => $suffix,
        ':username'    => $username,
        ':id'          => $id,
        ':password'    => $hashedPassword,
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}