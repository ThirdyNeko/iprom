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
        b.branch,
        a.required_count,
        a.assigned_count,
        a.timestamp,
        a.brand_name AS brand
    FROM [IPROM].[dbo].[assignment] a
    LEFT JOIN [IPROM].[dbo].[branches] b
        ON a.branch_name = b.branch_code
    WHERE a.brand_name = :brand
      AND required_count != assigned_count
      AND required_count > 0
    ORDER BY b.branch
");

$stmt->execute([':brand' => $brand]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);