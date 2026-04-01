<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

$sql = "
    SELECT
        a.branch_name,
        a.brand_name,
        a.required_count,
        COUNT(e.id) AS assigned_count
    FROM assignment a
    LEFT JOIN employee_info e
        ON e.branch = a.branch_name
       AND e.brand  = a.brand_name
       AND e.status = 'ACTIVE'
    GROUP BY
        a.branch_name,
        a.brand_name,
        a.required_count
    HAVING COUNT(e.id) < a.required_count
    ORDER BY a.branch_name, a.brand_name
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$branches = [];
$brands = [];

foreach ($rows as $row) {
    $branches[] = $row['branch_name'];
    $brands[]   = $row['brand_name'];
}

echo json_encode([
    'branches' => array_values(array_unique($branches)),
    'brands'   => array_values(array_unique($brands))
]);