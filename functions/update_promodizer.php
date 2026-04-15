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

if (!$id) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid employee ID']);
    exit;
}

// =========================
// FETCH CURRENT VALUES (IMPORTANT FIX)
// =========================
$stmt = $pdo->prepare("
    SELECT roving_group_id, multi_brand_group_id
    FROM employee_info
    WHERE id = ?
");
$stmt->execute([$id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {
    echo json_encode(['status' => 'danger', 'message' => 'Employee not found']);
    exit;
}

// =========================
// ALWAYS CONTROLLED
// =========================
$status = 'ACTIVE';

$employment_status = strtoupper(trim($_POST['employment_status'] ?? ''));
$reason_for_update = strtoupper(trim($_POST['reason_update'] ?? ''));

$remarks = trim($_POST['remarks'] ?? '');

$last_updated_by  = $_SESSION['username'] ?? 'System';
$last_assigned_by = $_POST['last_assigned_by'] ?? null;

// =========================
// SAFE GROUP INPUT HANDLING (FIXED)
// =========================
$roving_group_id = $current['roving_group_id'];
$multi_brand_group_id = $current['multi_brand_group_id'];

/**
 * Only update IF frontend actually sends data
 */
if (isset($_POST['branches'])) {

    $branches = json_decode($_POST['branches'], true);
    if (!is_array($branches)) $branches = [];

    if (!empty($branches)) {

        $roving_group_id = findGroup(
            $pdo,
            'branch_name',
            $branches,
            'roving_group_id'
        );

        if (!$roving_group_id) {
            echo json_encode([
                'status' => 'danger',
                'message' => 'Invalid branch combination'
            ]);
            exit;
        }
    }
}

if (isset($_POST['brands'])) {

    $brands = json_decode($_POST['brands'], true);
    if (!is_array($brands)) $brands = [];

    if (!empty($brands)) {

        $multi_brand_group_id = findGroup(
            $pdo,
            'brand_name',
            $brands,
            'multi_brand_group_id'
        );

        if (!$multi_brand_group_id) {
            echo json_encode([
                'status' => 'danger',
                'message' => 'Invalid brand combination'
            ]);
            exit;
        }
    }
}

// =========================
// DATE VALUES (SAFE)
// =========================
$start_date = (!empty($_POST['start_date'])) ? $_POST['start_date'] : null;
$end_date   = (!empty($_POST['end_date'])) ? $_POST['end_date'] : null;

$date_separated = (!empty($_POST['date_separated'])) ? $_POST['date_separated'] : null;
$date_of_return = (!empty($_POST['date_returned'])) ? $_POST['date_returned'] : null;

$sub_status = $_POST['sub_status'] ?? null;

// =========================
// VALIDATION
// =========================
$today = strtotime(date('Y-m-d'));

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
// EMPLOYMENT RULES
// =========================
$empStatusUpper = $employment_status;

if (in_array($empStatusUpper, ['RELIEVER', 'SEASONAL'])) {
    if (!$start_date || !$end_date) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Start and End date are required for Reliever/Seasonal'
        ]);
        exit;
    }
} else {
    $start_date = null;
    $end_date   = null;
}

// =========================
// STATUS LOGIC
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

$dateSeparatedValue = $date_separated ? strtotime($date_separated) : null;

if ($isInactiveReason) {

    if (!$dateSeparatedValue || $dateSeparatedValue <= $today) {
        $status = 'INACTIVE';
    } else {
        $status = 'ACTIVE';
    }

} else if (in_array($empStatusUpper, ['RELIEVER', 'SEASONAL']) && $start_date && $end_date) {

    $start = strtotime($start_date);
    $end   = strtotime($end_date);

    if ($start > $today || $end < $today) {
        $status = 'INACTIVE';
    } else {
        $status = 'ACTIVE';
    }
}

if ($reason_for_update === 'MATERNITY LEAVE' && $date_of_return) {
    if (strtotime($date_of_return) <= $today) {
        $status = 'ACTIVE';
    }
}

// =========================
// EXECUTE PROCEDURE (SAFE)
// =========================
try {
    $stmt = $pdo->prepare("
        EXEC update_employee
            @id = :id,
            @status = :status,
            @sub_status = :sub_status,
            @employment_status = :employment_status,
            @reason_for_update = :reason_for_update,
            @start_date = :start_date,
            @end_date = :end_date,
            @date_separated = :date_separated,
            @date_of_return = :date_of_return,
            @remarks = :remarks,
            @last_updated_by = :last_updated_by,
            @last_assigned_by = :last_assigned_by,
            @roving_group_id = :roving_group_id,
            @multi_brand_group_id = :multi_brand_group_id
    ");

    $stmt->execute([
        ':id' => $id,
        ':status' => $status,
        ':sub_status' => $sub_status,
        ':employment_status' => $employment_status,
        ':reason_for_update' => $reason_for_update,
        ':start_date' => $start_date,
        ':end_date' => $end_date,
        ':date_separated' => $date_separated,
        ':date_of_return' => $date_of_return,
        ':remarks' => $remarks,
        ':last_updated_by' => $last_updated_by,
        ':last_assigned_by' => $last_assigned_by,
        ':roving_group_id' => $roving_group_id,
        ':multi_brand_group_id' => $multi_brand_group_id
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Employee updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'danger',
        'message' => $e->getMessage()
    ]);
}

// =========================
// HELPERS
// =========================
function findGroup($pdo, $column, $values, $groupColumn) {
    if (empty($values)) return null;

    $placeholders = implode(',', array_fill(0, count($values), '?'));

    $stmt = $pdo->prepare("
        SELECT TOP 1 $groupColumn
        FROM assignment
        WHERE $column IN ($placeholders)
        GROUP BY $groupColumn
        HAVING COUNT(*) = ?
    ");

    $stmt->execute(array_merge($values, [count($values)]));

    return $stmt->fetchColumn() ?: null;
}