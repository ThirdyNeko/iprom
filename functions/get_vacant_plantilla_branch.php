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
$branchFilter = $isAll ? '' : 'AND a.branch_name = :branch';

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
    WHERE required_count != assigned_count
      AND required_count > 0
      $branchFilter
    ORDER BY b.branch, a.brand_name
");

if (!$isAll) $stmt->bindParam(':branch', $branch);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);