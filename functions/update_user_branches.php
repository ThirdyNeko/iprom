<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$id       = trim($_POST['id'] ?? '');
$branches = trim($_POST['branches'] ?? '');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'No id provided.']);
    exit;
}

try {
    $pdo = qa_db();
    $stmt = $pdo->prepare("UPDATE users SET branch = :branch, updated_at = GETDATE() WHERE id = :id");
    $stmt->execute([
        ':branch' => $branches,   // empty string clears all
        ':id'     => $id,
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}