<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/require_login.php';

header('Content-Type: application/json');

$pdo = qa_db();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$sql = "SELECT 
            bl.id,
            bl.first_name,
            bl.middle_name,
            bl.last_name,
            bl.suffix,
            bl.gender,
            bl.birthday,
            bl.marital_status,
            br.branch AS branch,
            bl.brand,
            bl.employment_status,
            bl.end_date,
            bl.remarks,
            bl.employee_id,
            br.region
        FROM blacklisted bl
        LEFT JOIN branches br ON br.branch_code = bl.branch
        WHERE bl.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode(['success' => true, 'data' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Record not found']);
}