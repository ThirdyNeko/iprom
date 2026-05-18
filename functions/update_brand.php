<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

try {

    // =========================
    // GET INPUTS
    // =========================
    $id = $_POST['id'] ?? null;
    $agency = $_POST['agency'] ?? null;

    // =========================
    // VALIDATION
    // =========================
    if (!$id) {
        echo json_encode([
            "success" => false,
            "message" => "ID is required"
        ]);
        exit;
    }

    // clean agency
    $agency = strtoupper(trim($agency ?? ''));

    // =========================
    // UPDATE ONLY AGENCY
    // =========================
    $stmt = $pdo->prepare("
        UPDATE brands
        SET agency = :agency
        WHERE id = :id
    ");

    $stmt->execute([
        ':agency' => $agency,
        ':id' => $id
    ]);

    // =========================
    // RESPONSE
    // =========================
    echo json_encode([
        "success" => true,
        "message" => "Agency updated successfully"
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}