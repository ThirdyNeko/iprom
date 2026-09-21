<?php
include '../config/db.php';
$pdo = qa_db();

$stmt = $pdo->query("
    SELECT branch_code, branch
    FROM IPROM.dbo.branches
    WHERE status = 1
");

$data = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data[trim($row['branch_code'])] = trim($row['branch']);
}

echo json_encode($data);