<?php
header('Content-Type: application/json');

include '../config/db.php';

$pdo = qa_db();

$id = $_POST['id'] ?? null;

if (!$id) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid agency.'
    ]);

    exit;
}

$stmt = $pdo->prepare("
    DELETE FROM agencies
    WHERE id = ?
");

$stmt->execute([$id]);

echo json_encode([
    'success' => true
]);