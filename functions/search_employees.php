<?php
require '../config/db.php';

$pdo = qa_db();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$search = "%$q%";

$sql = "
    SELECT TOP 20 employee_id, first_name, last_name
    FROM employee_info
    WHERE 
        first_name LIKE ?
        OR last_name LIKE ?
        OR CAST(employee_id AS VARCHAR) LIKE ?
    ORDER BY first_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$search, $search, $search]);

$results = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        'id' => $row['employee_id'],
        'text' => $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['employee_id'] . ')'
    ];
}

echo json_encode(['results' => $results]);