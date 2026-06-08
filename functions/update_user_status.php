<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$username = trim($_POST['username'] ?? '');
$status   = trim($_POST['status']   ?? '');

if (!$username || !in_array($status, ['ACTIVE', 'INACTIVE'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    $pdo = qa_db();
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE username = ?");
    $stmt->execute([$status, $username]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}