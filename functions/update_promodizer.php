<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request method']);
    exit;
}

// =========================
// POST VALUES
// =========================
$id = $_POST['id'] ?? null;

$status            = trim($_POST['status'] ?? 'ACTIVE');
$reason_for_update = trim($_POST['reason_update'] ?? '');

$date_separated = !empty($_POST['date_separated'])
    ? $_POST['date_separated']
    : null;

$date_of_return = !empty($_POST['date_returned'])
    ? $_POST['date_returned']
    : null;

$remarks = trim($_POST['remarks'] ?? '');

$last_updated_by = $_SESSION['username'] ?? 'System';

// OPTIONAL (not currently used in UI, but supported by SP)
$last_assigned_by = $_POST['last_assigned_by'] ?? null;
$start_date       = $_POST['start_date'] ?? null;
$end_date         = $_POST['end_date'] ?? null;

// =========================
// VALIDATION
// =========================
if (!$id) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid employee ID']);
    exit;
}

// =========================
// STATUS LOGIC (optional override safety)
// =========================
$inactiveReasons = [
    'RESIGNED',
    'PULL-OUT / TERMINATED',
    'AWOL',
    'END OF CONTRACT',
    'BLACKLISTED',
    'RETRENCHMENT'
];

if (in_array(strtoupper($reason_for_update), $inactiveReasons)) {
    $status = 'INACTIVE';
}

// =========================
// EXECUTE STORED PROCEDURE
// =========================
try {
    $stmt = $pdo->prepare("
        EXEC update_employee
            @id = :id,
            @status = :status,
            @reason_for_update = :reason_for_update,
            @start_date = :start_date,
            @end_date = :end_date,
            @date_separated = :date_separated,
            @date_of_return = :date_of_return,
            @remarks = :remarks,
            @last_updated_by = :last_updated_by,
            @last_assigned_by = :last_assigned_by
    ");

    $stmt->execute([
        ':id'                => $id,
        ':status'            => $status,
        ':reason_for_update' => $reason_for_update,
        ':start_date'        => $start_date,
        ':end_date'          => $end_date,
        ':date_separated'    => $date_separated,
        ':date_of_return'    => $date_of_return,
        ':remarks'           => $remarks,
        ':last_updated_by'   => $last_updated_by,
        ':last_assigned_by'  => $last_assigned_by
    ]);

    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Employee updated successfully',
        'data'    => $updated
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'danger',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}