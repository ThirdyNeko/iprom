<?php
require_once '../config/db.php';

$pdo = qa_db();

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        employee_id,
        reason_for_update,
        remarks,
        update_date
    FROM employee_reason_history
    WHERE employee_id = :id
    ORDER BY update_date DESC
");

$stmt->execute([':id' => $id]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);