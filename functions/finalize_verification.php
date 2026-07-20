<?php
// ─────────────────────────────────────────────────────────────
// functions/finalize_verification.php
// Thin wrapper around dbo.sp_finalize_verification.
// Expects JSON POST: { employee_id, branch_code, loa_id, remarks? }
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
$remarks    = trim($input['remarks'] ?? '');

if ($employeeId === '' || $branchCode === '' || $loaId === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing employee, branch, or LOA record information.',
    ]);
    exit;
}

// Adjust to however the logged-in user is actually tracked in session.
$updatedBy = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'SYSTEM';

$remarksParam = $remarks !== '' ? $remarks : null;
$loaIdInt     = (int) $loaId;

try {
    // Named "@param = :name OUTPUT" syntax doesn't survive PDO_ODBC's
    // placeholder substitution correctly (the OUTPUT keyword ends up in
    // the wrong spot from the driver's point of view). The ODBC call
    // escape syntax with positional "?" placeholders is what actually
    // works for output params on this driver.
    $stmt = $pdo->prepare("
        {CALL dbo.finalize_verification (?, ?, ?, ?, ?, ?, ?, ?)}
    ");

    $outStatus  = null;
    $outSuccess = null;
    $outMessage = null;

    // Positional order MUST match the CREATE PROCEDURE parameter order exactly.
    $stmt->bindParam(1, $employeeId);
    $stmt->bindParam(2, $branchCode);
    $stmt->bindParam(3, $loaIdInt, PDO::PARAM_INT);
    $stmt->bindParam(4, $remarksParam);
    $stmt->bindParam(5, $updatedBy);
    $stmt->bindParam(6, $outStatus, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 20);
    $stmt->bindParam(7, $outSuccess, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 4);
    $stmt->bindParam(8, $outMessage, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 500);

    $stmt->execute();
    $stmt->closeCursor();

    echo json_encode([
        'success' => (bool) $outSuccess,
        'status'  => $outStatus,
        'message' => $outMessage,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status: ' . $e->getMessage(),
    ]);
}