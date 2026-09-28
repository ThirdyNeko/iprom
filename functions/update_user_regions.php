<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$id      = trim($_POST['id'] ?? '');
$regions = trim($_POST['regions'] ?? '');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'No id provided.']);
    exit;
}

try {
    $pdo = qa_db();
    $stmt = $pdo->prepare("UPDATE users SET region = :region, updated_at = GETDATE() WHERE id = :id");
    $stmt->execute([
        ':region' => $regions,   // empty string clears all
        ':id'     => $id,
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}