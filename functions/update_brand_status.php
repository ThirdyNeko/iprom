<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$pdo = qa_db();

$id     = trim($_POST['id'] ?? '');
$status = intval($_POST['status'] ?? 0);

if ($id === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid brand ID.']);
    exit;
}

if (!in_array($status, [0, 1], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE brands SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}