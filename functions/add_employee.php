<?php
session_start(); // Make sure session is started
require_once '../config/db.php';
$pdo = qa_db();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            EXEC add_employee
                @first_name = :first_name,
                @last_name  = :last_name,
                @branch     = :branch,
                @brand      = :brand,
                @status     = :status,
                @assigned_by = :assigned_by
        ");

        $stmt->execute([
            ':first_name'  => $_POST['first_name'],
            ':last_name'   => $_POST['last_name'],
            ':branch'      => $_POST['branch'] ?: null,
            ':brand'       => $_POST['brand'] ?: null,
            ':status'      => $_POST['status'] ?: null,
            ':assigned_by' => $_SESSION['username'] ?? 'system' // fallback
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $newId = $result['new_id'] ?? null;

        echo json_encode([
            'status' => 'success',
            'message' => 'Employee added successfully!',
            'new_id' => $newId
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Error: '.$e->getMessage()
        ]);
    }
}