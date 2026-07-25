<?php
// ─────────────────────────────────────────────────────────────
// functions/cancel_loa.php
// Hard-deletes a letters_of_advice row after confirming:
//   1. The requesting session is admin or super_admin (server-side
//      re-check — the Actions-button visibility client-side is UI
//      convenience only and must never be trusted as the real gate).
//   2. The submitted LOA code matches the record being deleted.
//
// Expects JSON POST body: { employee_id, loa_id, loa_code }
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

if (!$employeeId || !$loaId || !preg_match('/^[A-Z]{4}-\d{6}$/', $loaCode)) {
    $response['message'] = 'Invalid request.';
    echo json_encode($response);
    exit;
}

try {
    $pdo = qa_db();

    // letters_of_advice has no loa_code column of its own -- the code
    // of record lives on employee_info, so confirm the match via a
    // join rather than against the LOA row directly. PK on
    // letters_of_advice is [id], not loa_id.
    $stmt = $pdo->prepare("
        SELECT loa.id
        FROM letters_of_advice loa
        INNER JOIN employee_info e ON e.employee_id = loa.employee_id
        WHERE loa.id = :loa_id
          AND loa.employee_id = :employee_id
          AND e.loa_code = :loa_code
    ");
    $stmt->execute([
        ':loa_id'      => $loaId,
        ':employee_id' => $employeeId,
        ':loa_code'    => $loaCode,
    ]);

    if (!$stmt->fetch()) {
        $response['message'] = 'LOA code does not match our records.';
        echo json_encode($response);
        exit;
    }

    $del = $pdo->prepare("
        DELETE FROM letters_of_advice
        WHERE id = :loa_id
          AND employee_id = :employee_id
    ");
    $del->execute([
        ':loa_id'      => $loaId,
        ':employee_id' => $employeeId,
    ]);

    $response['success'] = true;
} catch (Exception $e) {
    error_log('cancel_loa.php error: ' . $e->getMessage());
    $response['message'] = 'A server error occurred while cancelling the LOA.';
}

echo json_encode($response);