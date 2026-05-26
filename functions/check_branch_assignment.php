<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

$branch = $_POST['branch_code'] ?? '';

try {
    $stmt = $pdo->prepare("
        SELECT brand_name, assigned_count
        FROM assignment
        WHERE branch_name = :branch
          AND required_count > 0
    ");
    $stmt->execute(['branch' => $branch]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        echo json_encode([
            "blocked" => true,
            "brands" => array_column($rows, 'brand_name')
        ]);
    } else {
        echo json_encode([
            "blocked" => false
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        "blocked" => true,
        "error" => $e->getMessage()
    ]);
}