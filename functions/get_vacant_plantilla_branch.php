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
        a.updated_at,
        a.brand_name AS brand,
        ei.latest_start_date,
        ei.latest_date_separated
    FROM assignment a
    LEFT JOIN branches b
        ON a.branch_name COLLATE DATABASE_DEFAULT = b.branch_code COLLATE DATABASE_DEFAULT

    OUTER APPLY (
        SELECT
            MAX(e.start_date) AS latest_start_date,
            MAX(e.date_separated) AS latest_date_separated
        FROM employee_info e
        WHERE e.branch = a.branch_name
          AND e.brand = a.brand_name
    ) ei

    WHERE a.required_count != a.assigned_count
      AND a.required_count > 0
      $branchFilter

    ORDER BY b.branch, a.brand_name
");

if (!$isAll) $stmt->bindParam(':branch', $branch);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);