<?php
// ─────────────────────────────────────────────────────────────
// functions/bulk_verify_loa.php
// Admin / super_admin only. Verifies multiple LOAs at once by
// calling dbo.finalize_verification directly for each one --
// i.e. the same finalization step the single-verify modal's Step 4
// uses, skipping LOA code entry (Step 1) and ID picture upload
// (Step 2) entirely. Status (ACTIVE vs QUEUED) is still decided
// by the stored procedure itself based on the record's start date,
// so bulk verification stays consistent with single verification.
//
// Expects JSON POST: { items: [ { loa_id, employee_id, branch_code }, ... ] }
// Returns: { success: bool, verified_count, failed_count, results: [...] }
// ─────────────────────────────────────────────────────────────

session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

// Server-side role gate. The Bulk Verify button/checkbox column being
// hidden client-side for non-admins is UI convenience only -- this is
// the actual authorization check.
$userRole = strtolower($_SESSION['role'] ?? '');
if (!in_array($userRole, ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You are not authorized to perform bulk verification.',
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];

if (!is_array($items) || count($items) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No LOAs were selected.',
    ]);
    exit;
}

// Sane upper bound so a malformed/huge payload can't hammer the SP in a loop.
if (count($items) > 200) {
    echo json_encode([
        'success' => false,
        'message' => 'Too many LOAs selected at once. Please verify in smaller batches.',
    ]);
    exit;
}

$updatedBy = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'SYSTEM';

$verifiedCount = 0;
$failedCount   = 0;
$results       = [];

foreach ($items as $item) {
    $employeeId = trim((string) ($item['employee_id'] ?? ''));
    $branchCode = trim((string) ($item['branch_code'] ?? ''));
    $loaId      = trim((string) ($item['loa_id'] ?? ''));

    if ($employeeId === '' || $branchCode === '' || $loaId === '') {
        $failedCount++;
        $results[] = [
            'loa_id'  => $loaId,
            'success' => false,
            'message' => 'Missing employee, branch, or LOA record information.',
        ];
        continue;
    }

    $loaIdInt     = (int) $loaId;
    // Bulk verification is issued as a single blanket action, not a
    // per-record note, so remarks is always null here -- matches
    // finalize_verification.php's behavior when remarks isn't supplied.
    $remarksParam = null;

    try {
        // Same ODBC call-escape + positional-placeholder pattern as
        // functions/finalize_verification.php -- named "OUTPUT" params
        // don't survive PDO_ODBC placeholder substitution correctly here.
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
            $verifiedCount++;
            $results[] = [
                'loa_id'  => $loaId,
                'success' => true,
                'status'  => $outStatus,
            ];
        } else {
            $failedCount++;
            $results[] = [
                'loa_id'  => $loaId,
                'success' => false,
                'message' => $outMessage ?: 'Verification failed.',
            ];
        }
    } catch (Exception $e) {
        $failedCount++;
        $results[] = [
            'loa_id'  => $loaId,
            'success' => false,
            'message' => 'Failed to update status: ' . $e->getMessage(),
        ];
    }
}

echo json_encode([
    'success'        => $verifiedCount > 0,
    'verified_count' => $verifiedCount,
    'failed_count'   => $failedCount,
    'results'        => $results,
]);