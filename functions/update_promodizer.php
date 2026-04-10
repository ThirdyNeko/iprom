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
$employment_status = trim($_POST['employment_status'] ?? null);
$reason_for_update = trim($_POST['reason_update'] ?? '');

$remarks = trim($_POST['remarks'] ?? '');

$last_updated_by = $_SESSION['username'] ?? 'System';
$last_assigned_by = $_POST['last_assigned_by'] ?? null;

// =========================
// DATE VALUES (SAFE)
// =========================
$start_date = (isset($_POST['start_date']) && $_POST['start_date'] !== '')
    ? $_POST['start_date']
    : null;

$end_date = (isset($_POST['end_date']) && $_POST['end_date'] !== '')
    ? $_POST['end_date']
    : null;

$date_separated = (isset($_POST['date_separated']) && $_POST['date_separated'] !== '')
    ? $_POST['date_separated']
    : null;

$date_of_return = (isset($_POST['date_returned']) && $_POST['date_returned'] !== '')
    ? $_POST['date_returned']
    : null;

// =========================
// VALIDATION
// =========================
if (!$id) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid employee ID']);
    exit;
}

$dateSeparatedValue = $date_separated ? strtotime($date_separated) : null;
$today = strtotime(date('Y-m-d'));

$inactiveReasons = [
    'RESIGNED',
    'PULL-OUT / TERMINATED',
    'AWOL',
    'END OF CONTRACT',
    'BLACKLISTED',
    'RETRENCHMENT',
    'TRANSFER',
    'MATERNITY LEAVE'
];

$isInactiveReason = in_array(strtoupper($reason_for_update), $inactiveReasons);

// =========================
// NEW RULE: DELAY INACTIVATION UNTIL DATE_SEPARATED
// =========================
if ($isInactiveReason) {

    // If no separation date → allow immediate INACTIVE
    if (!$dateSeparatedValue) {
        $status = 'INACTIVE';
    }

    // If separation date exists → only inactivate if date has arrived
    else if ($dateSeparatedValue <= $today) {
        $status = 'INACTIVE';
    }

    // Otherwise keep ACTIVE until separation date
    else {
        $status = 'ACTIVE';
    }
}

// =========================
// EXECUTE PROCEDURE
// =========================
try {
    $stmt = $pdo->prepare("
        EXEC update_employee
            @id = :id,
            @status = :status,
            @employment_status = :employment_status,
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
        ':employment_status' => $employment_status,
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
        'status' => 'success',
        'message' => 'Employee updated successfully',
        'data' => $updated
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'danger',
        'message' => $e->getMessage()
    ]);
}