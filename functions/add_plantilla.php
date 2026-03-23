<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

$pdo = qa_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $branch   = $_POST['branch_name'] ?? '';
    $brand    = $_POST['brand_name'] ?? '';
    $required = $_POST['required_count'] ?? 0;

    if (!$branch || !$brand || !$required) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'All fields are required.'
        ]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            EXEC add_assignment
                @branch_name    = :branch,
                @brand_name     = :brand,
                @required_count = :required,
                @manager_id     = :manager
        ");

        $stmt->execute([
            ':branch'   => $branch,
            ':brand'    => $brand,
            ':required' => $required,
            ':manager'  => $_SESSION['user_id']
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Plantilla added successfully.'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}