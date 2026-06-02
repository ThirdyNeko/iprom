<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$username = trim($_POST['username'] ?? '');
$branches = trim($_POST['branches'] ?? '');

if (!$username) {
    echo json_encode(['success' => false, 'message' => 'No username provided.']);
    exit;
}

try {
    $pdo = qa_db();
    $stmt = $pdo->prepare("UPDATE users SET branch = :branch, updated_at = GETDATE() WHERE username = :username");
    $stmt->execute([
        ':branch'   => $branches,   // empty string clears all
        ':username' => $username,
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}