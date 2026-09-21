<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$role = $_SESSION['role'] ?? '';

// Both admin and super_admin can see the count.
if (!in_array(strtolower($role), ['admin', 'super_admin'])) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $sql = "SELECT COUNT(*) FROM flagging_request WHERE is_checked = 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $count = (int)$stmt->fetchColumn();

    echo json_encode(['count' => $count]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['count' => 0, 'error' => 'Failed to fetch count']);
}