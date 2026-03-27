<?php
session_start();
$updated_by = $_SESSION['username']; // or whatever you store
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

$stmt = $pdo->prepare("EXEC update_required_count ?, ?, ?, ?");
$stmt->execute([$branch, $brand, $required, $updated_by]);

$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($result);