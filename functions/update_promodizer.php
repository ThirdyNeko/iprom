<?php
session_start(); // Ensure session is active
require_once '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request method']);
    exit;
}

// Get POST values
$id               = $_POST['id'] ?? null;
$reason_update    = trim($_POST['reason_update'] ?? '');
$date_separated   = trim($_POST['date_separated'] ?? null);
$date_returned    = trim($_POST['date_returned'] ?? null);
$last_updated_by  = $_SESSION['username'] ?? 'System';
$date_last_updated= trim($_POST['date_last_updated'] ?? date('Y-m-d'));
$remarks          = trim($_POST['remarks'] ?? '');

// Validation
if (!$id) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid employee ID']);
    exit;
}

// Optional: set default status based on reason_update
$status = 'Active';
if (in_array(strtolower($reason_update), ['resigned', 'pull-out/terminated', 'AWOL', 'end of contract', 'blacklisted', 'retrenchment'])) {
    $status = 'Inactive';
}

try {
    $stmt = $pdo->prepare("
        EXEC update_employee
            @id = :id,
            @reason_update = :reason_update,
            @date_separated = :date_separated,
            @date_returned = :date_returned,
            @last_updated_by = :last_updated_by,
            @date_last_updated = :date_last_updated,
            @remarks = :remarks,
            @status = :status
    ");

    $stmt->execute([
        ':id'               => $id,
        ':reason_update'    => $reason_update,
        ':date_separated'   => $date_separated,
        ':date_returned'    => $date_returned,
        ':last_updated_by'  => $last_updated_by,
        ':date_last_updated'=> $date_last_updated,
        ':remarks'          => $remarks,
        ':status'           => $status
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Employee updated successfully',
        'data' => [
            'id'               => $id,
            'reason_update'    => $reason_update,
            'date_separated'   => $date_separated,
            'date_returned'    => $date_returned,
            'last_updated_by'  => $last_updated_by,
            'date_last_updated'=> $date_last_updated,
            'remarks'          => $remarks,
            'status'           => $status
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}