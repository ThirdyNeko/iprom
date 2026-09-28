<?php
// ─────────────────────────────────────────────────────────────
// functions/direct_verify_loa.php
// regional_manager only. Verifies ONE LOA directly by calling
// dbo.finalize_verification -- the same finalization step the
// verify modal's last step uses -- skipping LOA code entry and
// ID picture upload. Status (ACTIVE vs QUEUED) is still decided
// by the stored procedure from the record's start date, so this
// stays consistent with the modal and bulk flows.
//
// Expects JSON POST: { loa_id, employee_id, branch_code }
// Returns: { success: bool, status?: string, message?: string }
// ─────────────────────────────────────────────────────────────

session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

// Server-side role gate. Hiding the button client-side is UI convenience
// only -- this is the actual authorization check. Admins keep using the
// modal flow (which enforces code + picture), so they're not allowed here.
$userRole = strtolower($_SESSION['role'] ?? '');
if ($userRole !== 'regional_manager') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You are not authorized to perform this action.',
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$employeeId = trim((string) ($input['employee_id'] ?? ''));
$branchCode = trim((string) ($input['branch_code'] ?? ''));
$loaId      = trim((string) ($input['loa_id'] ?? ''));

if ($employeeId === '' || $branchCode === '' || $loaId === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing employee, branch, or LOA record information.',
    ]);
    exit;
}

// Region scope: login.php resolves the regional manager's region into branch
// codes stored in user_branches. Fail closed -- no branches means no access.
$sessionBranches = array_values(array_filter(
    array_map('trim', $_SESSION['user_branches'] ?? [])
));

if (empty($sessionBranches) || !in_array($branchCode, $sessionBranches, true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'This LOA is outside your region.',
    ]);
    exit;
}

$updatedBy    = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'SYSTEM';
$loaIdInt     = (int) $loaId;
$remarksParam = null;

try {
    // Same ODBC call-escape + positional-placeholder pattern as
    // bulk_verify_loa.php / finalize_verification.php.
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

    if ($outSuccess) {
        echo json_encode([
            'success' => true,
            'status'  => $outStatus,
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $outMessage ?: 'Verification failed.',
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status: ' . $e->getMessage(),
    ]);
}