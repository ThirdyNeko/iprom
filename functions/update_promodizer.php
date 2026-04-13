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

// 🔥 Always controlled by backend
$status = 'ACTIVE';

// Normalize inputs
$employment_status = strtoupper(trim($_POST['employment_status'] ?? ''));
$reason_for_update = strtoupper(trim($_POST['reason_update'] ?? ''));

$remarks = trim($_POST['remarks'] ?? '');

$last_updated_by  = $_SESSION['username'] ?? 'System';
$last_assigned_by = $_POST['last_assigned_by'] ?? null;

// =========================
// DATE VALUES (SAFE)
// =========================
$start_date = (!empty($_POST['start_date'])) ? $_POST['start_date'] : null;
$end_date   = (!empty($_POST['end_date']))   ? $_POST['end_date']   : null;

$date_separated = (!empty($_POST['date_separated'])) ? $_POST['date_separated'] : null;
$date_of_return = (!empty($_POST['date_returned']))  ? $_POST['date_returned']  : null;

// =========================
// VALIDATION
// =========================
if (!$id) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid employee ID']);
    exit;
}

// Date helpers
$today = strtotime(date('Y-m-d'));
$dateSeparatedValue = $date_separated ? strtotime($date_separated) : null;

// =========================
// START/END DATE VALIDATION
// =========================
if ($start_date && $end_date) {
    if (strtotime($end_date) < strtotime($start_date)) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'End date cannot be earlier than start date'
        ]);
        exit;
    }
}

// =========================
// EMPLOYMENT TYPE RULES
// =========================
$empStatusUpper = $employment_status;

// Require dates for reliever/seasonal
if (in_array($empStatusUpper, ['RELIEVER', 'SEASONAL'])) {
    if (!$start_date || !$end_date) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Start and End date are required for Reliever/Seasonal'
        ]);
        exit;
    }
} else {
    // Clear dates if not applicable
    $start_date = null;
    $end_date   = null;
}

// =========================
// INACTIVE REASONS
// =========================
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

$isInactiveReason = in_array($reason_for_update, $inactiveReasons);

// =========================
// STATUS LOGIC (FINAL ORDER)
// =========================

// 1. REASON-BASED (HIGHEST PRIORITY)
if ($isInactiveReason) {

    if (!$dateSeparatedValue) {
        $status = 'INACTIVE';
    } 
    else if ($dateSeparatedValue <= $today) {
        $status = 'INACTIVE';
    } 
    else {
        $status = 'ACTIVE';
    }
}

// =========================
// CONTRACT-BASED LOGIC (FIXED)
// =========================
else if (in_array($empStatusUpper, ['RELIEVER', 'SEASONAL']) && $start_date && $end_date) {

    $start = strtotime($start_date);
    $end   = strtotime($end_date);

    // ❌ NOT YET STARTED
    if ($start > $today) {
        $status = 'INACTIVE';
    }

    // ❌ ALREADY ENDED
    else if ($end < $today) {
        $status = 'INACTIVE';
    }

    // ✅ ACTIVE PERIOD
    else {
        $status = 'ACTIVE';
    }
}

// 3. OPTIONAL: MATERNITY AUTO-RETURN
if ($reason_for_update === 'MATERNITY LEAVE' && $date_of_return) {
    if (strtotime($date_of_return) <= $today) {
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