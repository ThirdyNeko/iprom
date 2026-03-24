<?php
session_start(); // Make sure session is started
require_once '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'danger','message'=>'Invalid request method']);
    exit;
}

// Get POST values
$id         = $_POST['id'] ?? null;
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$branch     = trim($_POST['branch'] ?? '') ?: null;
$brand      = trim($_POST['brand'] ?? '') ?: null;
$status     = $_POST['status'] ?? null;
$assigned_by = $_SESSION['username'] ?? 'System';

// Validation
if (!$id) {
    echo json_encode(['status'=>'danger','message'=>'Invalid employee ID']);
    exit;
}

// Dynamically set status if not provided
if (!$status) {
    $status = ($branch && $brand) ? 'Active' : 'Inactive';
}

try {
    $stmt = $pdo->prepare("
        EXEC update_employee
            @id = :id,
            @first_name = :first_name,
            @last_name  = :last_name,
            @branch = :branch,
            @brand = :brand,
            @status = :status,
            @last_assigned_by = :assigned_by
    ");

    $stmt->execute([
        ':id'           => $id,
        ':first_name'   => $first_name,
        ':last_name'    => $last_name,
        ':branch'       => $branch,
        ':brand'        => $brand,
        ':status'       => $status,
        ':assigned_by'  => $assigned_by
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Employee updated successfully',
        'data' => [
            'id' => $id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'branch' => $branch,
            'brand' => $brand,
            'status' => $status
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}