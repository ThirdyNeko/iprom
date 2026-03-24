<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');
$assigned_by = $_SESSION['username'] ?? 'System';

try {
    $stmt = $pdo->prepare("
        EXEC add_employee
            @first_name = :first_name,
            @last_name  = :last_name,
            @branch     = :branch,
            @brand      = :brand,
            @status     = :status,
            @assigned_by= :assigned_by
    ");

    $stmt->execute([
        ':first_name' => $_POST['first_name'],
        ':last_name'  => $_POST['last_name'],
        ':branch'     => $_POST['branch'] ?: null,
        ':brand'      => $_POST['brand'] ?: null,
        ':status'     => $_POST['status'] ?: null,
        ':assigned_by'=> $assigned_by
    ]);

    // ✅ Return JSON manually since no result set
    echo json_encode(['status' => 'success', 'message' => 'Employee added successfully!']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}