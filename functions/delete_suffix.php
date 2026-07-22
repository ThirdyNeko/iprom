<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$pdo = qa_db();

$id = trim($_POST['id'] ?? '');

if ($id === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid suffix.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM suffix WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Suffix deleted successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}