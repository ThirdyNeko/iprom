<?php
// ─────────────────────────────────────────────────────────────
// functions/finalize_verification.php
// 1. Sets the employee's status for the selected LOA's branch:
//    - start_date is today or in the past -> ACTIVE
//    - start_date is in the future        -> QUEUED
// 2. Deletes the now-verified row from letters_of_advice (identified
//    by its own [id], i.e. the loa_id passed in from the DataTable).
// Both steps run in one transaction so a failure on either leaves
// the DB untouched.
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
$loaId      = trim($input['loa_id'] ?? '');

if ($employeeId === '' || $branchCode === '' || $loaId === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing employee, branch, or LOA record information.',
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
    $stmt->closeCursor(); // release the result set before opening a transaction

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

    $pdo->beginTransaction();

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
    $upd->closeCursor();

    // Verification is complete -> remove the now-consumed LOA record.
    $del = $pdo->prepare("
        DELETE FROM [IPROM_TEST].[dbo].[letters_of_advice]
        WHERE [id] = :loa_id
    ");
    $del->execute([':loa_id' => $loaId]);
    $del->closeCursor();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'status'  => $status,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status: ' . $e->getMessage(),
    ]);
}