<?php
session_start();
$updated_by = $_SESSION['username']; // or whatever you store
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

    $stmt = $pdo->prepare("EXEC unassign_employee ?, ?");
    $stmt->execute([$id, $updated_by]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}