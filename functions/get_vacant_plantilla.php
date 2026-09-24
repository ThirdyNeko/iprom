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
      $brandFilter

    ORDER BY a.brand_name, b.branch
");

if (!$isAll) $stmt->bindParam(':brand', $brand);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);