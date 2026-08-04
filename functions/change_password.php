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

// 🔒 Enforce password strength
function validatePasswordStrength(string $password, string $username = ''): array {
    $errors = [];

    if (strlen($password) < 12) {
        $errors[] = "at least 12 characters";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "an uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "a lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "a number";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "a special character";
    }
    if ($username !== '' && stripos($password, $username) !== false) {
        $errors[] = "not contain your username";
    }
    if (preg_match('/^(.)\1+$/', $password) || preg_match('/^(0123456789|12345678|abcdefgh|password)/i', $password)) {
        $errors[] = "not be a common/predictable pattern";
    }

    return $errors;
}

$strengthErrors = validatePasswordStrength($newPassword, $_SESSION['username'] ?? '');
if (!empty($strengthErrors)) {
    echo json_encode([
        'status'  => 'danger',
        'message' => 'Password must have ' . implode(', ', $strengthErrors) . '.'
    ]);
    exit;
}

try {
    // ✅ Look up by user_id (unique, unambiguous)
    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $_SESSION['user_id']
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

    // 🔒 Don't allow reusing the same password
    if (password_verify($newPassword, $user['password'])) {
        echo json_encode(['status' => 'danger', 'message' => 'New password must be different from your current password.']);
        exit;
    }

    // Hash new password
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // ✅ Update by id, not username
    $update = $pdo->prepare("
        UPDATE users
        SET password = :password,
            first_login = 0
        WHERE id = :id
    ");

    $update->execute([
        ':password' => $newHashedPassword,
        ':id'       => $_SESSION['user_id']
    ]);

    // update session so UI updates immediately
    $_SESSION['first_login'] = 0;

    echo json_encode(['status' => 'success', 'message' => 'Password changed successfully!']);
    exit;

} catch (PDOException $e) {
    echo json_encode(['status' => 'danger', 'message' => 'Database error']);
    exit;
}