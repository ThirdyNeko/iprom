<?php
header('Content-Type: application/json');
include '../config/db.php';

try {
    $pdo = qa_db();

    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid ID"
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE employee_info
        SET branch = 'Unassigned',
            brand = 'Unassigned',
            status = 'Inactive'
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    echo json_encode([
        "status" => "success",
        "message" => "Employee unassigned successfully"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}