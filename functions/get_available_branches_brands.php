<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';
$pdo = qa_db();

$sessionBranches = !empty($_SESSION['branch'])
    ? array_map('trim', explode(',', $_SESSION['branch']))
    : [];

$sql = "
    SELECT
        a.branch_name AS branch_code,
        b.branch AS branch_name,
        b.corpo,
        a.brand_name,
        a.required_count,
        COUNT(e.id) AS assigned_count
    FROM assignment a
    LEFT JOIN IPROM.dbo.branches b
        ON b.branch_code = a.branch_name
    LEFT JOIN employee_info e
        ON e.branch = a.branch_name
       AND e.brand = a.brand_name
       AND e.status = 'ACTIVE'
    GROUP BY
        a.branch_name,
        b.branch,
        b.corpo,
        a.brand_name,
        a.required_count
    ORDER BY
        b.branch,
        a.brand_name
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Filter to session branches if restricted
if (!empty($sessionBranches)) {
    $rows = array_values(array_filter(
        $rows,
        fn($r) => in_array(trim($r['branch_code']), $sessionBranches)
    ));
}

echo json_encode($rows);