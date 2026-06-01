<?php
include '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

$branch = $_GET['branch'] ?? '';

if (!$branch) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        ei.first_name,
        ei.middle_name,
        ei.last_name,
        ei.suffix,
        ei.gender,
        ei.birthday,
        ei.date_hired,
        b.branch       AS branch,
        ei.brand,
        ei.status,
        ei.employment_status,
        ei.sub_status,
        ei.agency,
        ei.corpo,
        ei.assignment_date,
        ei.last_assigned_by
    FROM [IPROM].[dbo].[employee_info] ei
    LEFT JOIN [IPROM].[dbo].[branches] b
        ON ei.branch = b.branch_code
    WHERE ei.branch = :branch
      AND (ei.hidden = 0 OR ei.hidden IS NULL)
      AND (ei.status = 'Active' OR ei.status = 'Probationary')
    ORDER BY ei.brand
");

$stmt->execute([':branch' => $branch]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);