<?php
include '../config/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['employee_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Missing employee_id"
    ]);
    exit;
}

$employee_id = $data['employee_id'];

try {
    $stmt = $pdo->prepare("
        UPDATE dbo.employee_info
        SET print_loa = 0
        WHERE employee_id = :employee_id
    ");

    $stmt->execute([
        ":employee_id" => $employee_id
    ]);

    echo json_encode([
        "success" => true
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}