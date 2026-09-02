<?php
session_start();
header('Content-Type: application/json');

include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$role = $_SESSION['role'] ?? '';

// Only admin gets this bubble
if (strtolower(trim($role)) !== 'admin') {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $sql = "SELECT COUNT(*)
            FROM blacklist_request
            WHERE status = 'Pending'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $count = (int) $stmt->fetchColumn();

    echo json_encode([
        'count' => $count
    ]);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'count' => 0,
        'error' => 'Failed to fetch count'
    ]);
}