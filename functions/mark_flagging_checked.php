<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$role = $_SESSION['role'] ?? '';

// Only admin opening a row marks it checked — super_admin can view
// the count/badge but their opens don't count.
if (strtolower($role) !== 'admin') {
    echo json_encode(['success' => true, 'skipped' => true]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
    exit;
}

try {
    $sql = "UPDATE flagging_request SET is_checked = 1 WHERE id = ? AND is_checked = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to mark as checked.']);
}