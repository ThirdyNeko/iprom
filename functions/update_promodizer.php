<?php
require_once '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $branch = $_POST['branch'] ?: null;
    $brand = $_POST['brand'] ?: null;
    $status = $_POST['status'] ?? 'Inactive';

    if(!$id) {
        echo json_encode(['status'=>'danger','message'=>'Invalid employee ID']);
        exit;
    }

    $stmt = $pdo->prepare("
        EXEC update_employee
            @id = :id,
            @branch = :branch,
            @brand = :brand,
            @status = :status
    ");

    try {
        $stmt->execute([
            ':id' => $id,
            ':branch' => $branch,
            ':brand' => $brand,
            ':status' => $status
        ]);

        echo json_encode(['status'=>'success','message'=>'Employee updated successfully.']);
    } catch(Exception $e) {
        echo json_encode(['status'=>'danger','message'=>$e->getMessage()]);
    }
}