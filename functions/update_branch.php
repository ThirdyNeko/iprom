<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

try {

    // =========================
    // GET INPUTS
    // =========================
    $branchCode = $_POST['branch_code'] ?? null;
    $director   = $_POST['director'] ?? null;

    // =========================
    // VALIDATION
    // =========================
    if (!$branchCode) {
        echo json_encode([
            "success" => false,
            "message" => "Branch code is required"
        ]);
        exit;
    }

    // clean director
    $director = strtoupper(trim($director ?? ''));

    // =========================
    // UPDATE ONLY DIRECTOR
    // =========================
    $stmt = $pdo->prepare("
        UPDATE branches
        SET director = :director
        WHERE branch_code = :branch_code
    ");

    $stmt->execute([
        ':director'    => $director,
        ':branch_code' => $branchCode
    ]);

    // =========================
    // RESPONSE
    // =========================
    echo json_encode([
        "success" => true,
        "message" => "Director updated successfully"
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}