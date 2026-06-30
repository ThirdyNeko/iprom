<?php
include '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

$brand = $_GET['brand'] ?? '';

if (!$brand) {
    echo json_encode([]);
    exit;
}

$isAll = $brand === 'ALL';
$brandFilter = $isAll ? '' : 'AND a.brand_name COLLATE DATABASE_DEFAULT = :brand COLLATE DATABASE_DEFAULT';

$stmt = $pdo->prepare("
    SELECT
        b.branch,
        a.required_count,
        a.assigned_count,
        a.timestamp,
        a.brand_name AS brand
    FROM [IPROM_TEST].[dbo].[assignment] a
    LEFT JOIN [IPROM_TEST].[dbo].[branches] b
        ON a.branch_name COLLATE DATABASE_DEFAULT = b.branch_code COLLATE DATABASE_DEFAULT
    WHERE required_count = assigned_count
      AND required_count > 0
      $brandFilter
    ORDER BY a.brand_name, b.branch
");

if (!$isAll) $stmt->bindParam(':brand', $brand);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);