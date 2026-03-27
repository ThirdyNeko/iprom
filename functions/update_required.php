<?php
include '../config/db.php';
$pdo = qa_db();

// Accept JSON input
$data = json_decode(file_get_contents("php://input"), true);

$branch = $data['branch'] ?? '';
$brand  = $data['brand'] ?? '';
$required = intval($data['required']);

if (!$branch || !$brand) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid input"
    ]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE assignment
    SET required_count = ?, updated_at = GETDATE()
    WHERE branch_name = ? AND brand_name = ?
");

$stmt->execute([$required, $branch, $brand]);

echo json_encode([
    "status" => "success",
    "message" => "Required updated successfully"
]);