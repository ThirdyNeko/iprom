<?php
include '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

$branch = $_GET['branch'] ?? '';

if (!$branch) {
    echo json_encode([]);
    exit;
}

$isAll = $branch === 'ALL';
$branchFilter = $isAll ? '' : 'AND a.branch_name COLLATE DATABASE_DEFAULT = :branch COLLATE DATABASE_DEFAULT';

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
      $branchFilter
    ORDER BY b.branch, a.brand_name
");

if (!$isAll) $stmt->bindParam(':branch', $branch);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);