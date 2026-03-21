<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'danger', 'message' => 'You must be logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request method.']);
    exit;
}

$currentPassword = trim($_POST['current_password'] ?? '');
$newPassword = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

if (!$currentPassword || !$newPassword || !$confirmPassword) {
    echo json_encode(['status' => 'danger', 'message' => 'All fields are required.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['status' => 'danger', 'message' => 'Passwords do not match.']);
    exit;
}

try {
    // ✅ Use username from session
    $stmt = $pdo->prepare("
        EXEC get_user_by_username @username = :username
    ");

    $stmt->execute([
        ':username' => $_SESSION['username']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'danger', 'message' => 'User not found.']);
        exit;
    }

    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        echo json_encode(['status' => 'danger', 'message' => 'Current password is incorrect.']);
        exit;
    }

    // Hash new password
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // ✅ Update using stored procedure
    $update = $pdo->prepare("
        EXEC update_user_password 
            @username = :username,
            @password = :password
    ");

    $update->execute([
        ':username' => $_SESSION['username'],
        ':password' => $newHashedPassword
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Password changed successfully!']);
    exit;

} catch (PDOException $e) {
    echo json_encode(['status' => 'danger', 'message' => 'Database error']);
    exit;
}