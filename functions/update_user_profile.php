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

$id         = trim($_POST['id'] ?? '');
$position   = trim($_POST['position'] ?? '');
$role       = trim($_POST['role'] ?? '');
$firstName  = trim($_POST['first_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$lastName   = trim($_POST['last_name'] ?? '');
$suffix     = trim($_POST['suffix'] ?? '');

$validRoles = ['staff', 'supervisor', 'branch_manager', 'admin', 'super_admin'];

if (!$id || !$position || !$firstName || !$lastName || !in_array($role, $validRoles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

// Username is always derived server-side from first + last name,
// never trusted from the client. Format: "FIRST LAST" uppercase.
$username = strtoupper(trim($firstName . ' ' . $lastName));

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
            updated_at  = GETDATE()
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
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}