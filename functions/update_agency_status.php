<?php
header('Content-Type: application/json');

include '../config/db.php';

$pdo = qa_db();

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || $status === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE agencies
    SET status = ?
    WHERE id = ?
");

$stmt->execute([$status, $id]);

echo json_encode([
    'success' => true
]);