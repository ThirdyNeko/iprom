<?php
require_once '../config/db.php';

$pdo = qa_db();

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT reason_for_update, update_date
    FROM employee_reason_history
    WHERE employee_id = ?
    ORDER BY update_date DESC
");

$stmt->execute([$id]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));