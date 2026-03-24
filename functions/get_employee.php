<?php
require_once '../config/db.php';
$pdo = qa_db();

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM employee_info
    WHERE id = :id
");
$stmt->execute([':id' => $id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($employee ?: []);