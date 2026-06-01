<?php
include '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

$brand = $_GET['brand'] ?? '';

if (!$brand) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        a.branch_name,
        a.required_count,
        a.assigned_count,
        a.timestamp
    FROM [IPROM].[dbo].[assignment] a
    WHERE a.brand_name = :brand
      AND required_count = assigned_count
      AND required_count > 0
    ORDER BY a.branch_name
");

$stmt->execute([':brand' => $brand]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);