<?php
session_start();
header('Content-Type: application/json');

include '../config/db.php';
$pdo = qa_db();

$updated_by = $_SESSION['username'] ?? 'system';

// Accept JSON input
$data = json_decode(file_get_contents("php://input"), true);

$branch = $data['branch'] ?? '';
$brand  = $data['brand'] ?? '';
$required = isset($data['required']) ? (int)$data['required'] : -1;

if (!$branch || !$brand || $required < 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid input"
    ]);
    exit;
}

try {

    // ✅ GET ASSIGNED COUNT FROM YOUR REAL TABLE
    $stmt = $pdo->prepare("
        SELECT assigned_count
        FROM assignment
        WHERE branch_name = ? AND brand_name = ?
    ");

    $stmt->execute([$branch, $brand]);
    $assigned = (int) $stmt->fetchColumn();

    if (!$assigned && $assigned !== 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Assignment record not found"
        ]);
        exit;
    }

    // 🚨 HARD RULE CHECK
    if ($required < $assigned) {
        echo json_encode([
            "status" => "error",
            "message" => "Required ($required) cannot be less than assigned ($assigned)"
        ]);
        exit;
    }

    // ✅ UPDATE MAIN TABLE
    $stmt = $pdo->prepare("
        UPDATE assignment
        SET required_count = ?,
            updated_at = GETDATE(),
            updated_by = ?
        WHERE branch_name = ? AND brand_name = ?
    ");

    $ok = $stmt->execute([$required, $updated_by, $branch, $brand]);

    echo json_encode([
        "status" => $ok ? "success" : "error",
        "message" => $ok ? "Updated successfully" : "Update failed"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Server error"
    ]);
}