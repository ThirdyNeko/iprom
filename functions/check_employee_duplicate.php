<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

$pdo = qa_db();

// read JSON input
$input = json_decode(file_get_contents('php://input'), true);

$first_name = strtoupper(trim($input['first_name'] ?? ''));
$last_name  = strtoupper(trim($input['last_name'] ?? ''));
$middle_name = strtoupper(trim($input['middle_name'] ?? ''));
$birthday   = $input['birthday'] ?? '';

if (!$first_name || !$last_name || !$birthday) {
    echo json_encode([
        'exists' => false,
        'error' => 'Missing required fields'
    ]);
    exit;
}

try {

    // 🔍 Check for existing employee
    $sql = "
        SELECT TOP 1
            id,
            employee_id,
            first_name,
            last_name,
            middle_name,
            birthday,
            status,
            reason_for_update
        FROM employee_info
        WHERE 
            UPPER(first_name) = :first_name
            AND UPPER(last_name) = :last_name
            AND ISNULL(UPPER(LTRIM(RTRIM(middle_name))), '') = :middle_name
            AND birthday = :birthday
        ORDER BY id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':first_name' => $first_name,
        ':last_name'  => $last_name,
        ':middle_name'=> $middle_name,
        ':birthday'   => $birthday
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            'exists' => true,
            'id' => $row['id'], // ✅ add this
            'employee_id' => $row['employee_id'],
            'status' => $row['status'],
            'reason_for_update' => $row['reason_for_update']
        ]);
    } else {
        echo json_encode([
            'exists' => false
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'exists' => false,
        'error' => $e->getMessage()
    ]);
}