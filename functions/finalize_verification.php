<?php
// ─────────────────────────────────────────────────────────────
// functions/finalize_verification.php
// Sets the employee's status for the selected LOA's branch:
//   - start_date is today or in the past -> ACTIVE
//   - start_date is in the future        -> QUEUED
// Expects JSON POST: { employee_id, branch_code, loa_id }
// Returns: { success: bool, status?: string, message?: string }
// ─────────────────────────────────────────────────────────────

session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$input = json_decode(file_get_contents('php://input'), true);

$employeeId = trim($input['employee_id'] ?? '');
$branchCode = trim($input['branch_code'] ?? '');
// $loaId is accepted for logging/traceability if needed later
$loaId = trim($input['loa_id'] ?? '');

if ($employeeId === '' || $branchCode === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing employee or branch information.',
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT start_date
        FROM employee_info
        WHERE employee_id = :employee_id
          AND branch = :branch_code
    ");
    $stmt->execute([
        ':employee_id' => $employeeId,
        ':branch_code' => $branchCode,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['start_date'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Start date not found for this employee.',
        ]);
        exit;
    }

    $startDate = new DateTime($row['start_date']);
    $startDate->setTime(0, 0, 0);

    $today = new DateTime('today');

    // start_date is today or in the past -> ACTIVE, else QUEUED
    $status = ($startDate <= $today) ? 'ACTIVE' : 'QUEUED';

    $upd = $pdo->prepare("
        UPDATE employee_info
        SET status = :status
        WHERE employee_id = :employee_id
          AND branch = :branch_code
    ");
    $upd->execute([
        ':status'      => $status,
        ':employee_id' => $employeeId,
        ':branch_code' => $branchCode,
    ]);

    echo json_encode([
        'success' => true,
        'status'  => $status,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status: ' . $e->getMessage(),
    ]);
}