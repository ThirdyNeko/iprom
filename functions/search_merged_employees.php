<?php
require '../config/db.php';

$pdo = qa_db();

$q = $_GET['q'] ?? '';

$stmt = $pdo->prepare("
    SELECT DISTINCT e.employee_id, e.first_name, e.last_name
    FROM employee_info e
    INNER JOIN employee_merge_log m
        ON e.employee_id = m.merged_employee_id
    WHERE m.unmerged_at IS NULL
    AND (
        e.first_name LIKE ? OR 
        e.last_name LIKE ? OR 
        e.employee_id LIKE ?
    )
");

$search = "%$q%";
$stmt->execute([$search, $search, $search]);

$results = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        'id' => $row['employee_id'],
        'text' => $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['employee_id'] . ')'
    ];
}

echo json_encode(['results' => $results]);