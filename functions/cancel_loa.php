<?php
// ─────────────────────────────────────────────────────────────
// functions/cancel_loa.php
// Cancels a letters_of_advice row via sp_cancel_loa, which:
//   1. Re-validates the LOA code server-side (never trust the
//      client-side Actions-button gate).
//   2. Logs a CANCELLED entry to employee_reason_history, with
//      the caller-supplied reason stored as remarks. A reason is
//      required — this endpoint rejects the request without one,
//      same as the SP itself would with a NULL/blank @remarks.
//   3. Deletes the letters_of_advice row.
// All inside one SP-managed transaction.
//
// Expects JSON POST body: { employee_id, loa_id, loa_code, remarks }
// Returns JSON: { success: bool, message?: string }
// ─────────────────────────────────────────────────────────────

session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$response = ['success' => false, 'message' => ''];

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'super_admin'], true)) {
    http_response_code(403);
    $response['message'] = 'You do not have permission to cancel LOA records.';
    echo json_encode($response);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true);
$employeeId = $input['employee_id'] ?? null;
$loaId      = $input['loa_id'] ?? null;
$loaCode    = trim($input['loa_code'] ?? '');
$remarks    = trim($input['remarks'] ?? '');

if (!$employeeId || !$loaId || !preg_match('/^[A-Z]{4}-\d{6}$/', $loaCode)) {
    $response['message'] = 'Invalid request.';
    echo json_encode($response);
    exit;
}

// Reason for cancellation is mandatory -- reject before ever hitting
// the SP rather than relying on it to silently accept a NULL.
if ($remarks === '') {
    $response['message'] = 'A reason for cancellation is required.';
    echo json_encode($response);
    exit;
}

// Adjust to whatever session key actually holds the display name.
$updatedBy = $_SESSION['username'] ?? $_SESSION['name'] ?? 'system';

try {
    $pdo = qa_db();

    $sql  = "{call cancel_loa(?, ?, ?, ?, ?, ?, ?)}";
    $stmt = $pdo->prepare($sql);

    $outSuccess = null;
    $outMessage = null;

    $stmt->bindParam(1, $employeeId, PDO::PARAM_STR);
    $stmt->bindParam(2, $loaId, PDO::PARAM_INT);
    $stmt->bindParam(3, $loaCode, PDO::PARAM_STR);
    $stmt->bindParam(4, $remarks, PDO::PARAM_STR);
    $stmt->bindParam(5, $updatedBy, PDO::PARAM_STR);
    $stmt->bindParam(6, $outSuccess, PDO::PARAM_BOOL | PDO::PARAM_INPUT_OUTPUT, 5);
    $stmt->bindParam(7, $outMessage, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 500);

    $stmt->execute();

    if ($outSuccess) {
        $response['success'] = true;
    } else {
        $response['message'] = $outMessage ?: 'LOA code does not match our records.';
    }
} catch (Exception $e) {
    error_log('cancel_loa.php error: ' . $e->getMessage());
    $response['message'] = 'A server error occurred while cancelling the LOA.';
}

echo json_encode($response);