<?php
/**
 * functions/unflag_request.php
 *
 * Unflags a flagging_request row via dbo.unflag_flagging_request, which
 * does the permission check, status check, and race-condition guard
 * atomically server-side. There is no approve/reject/cancel anymore —
 * this is the only status-changing action left.
 *
 * regional_manager additionally gets a branch-scope check here, because
 * the stored procedure has no way to know which branches make up their
 * region.
 */

session_start();
header('Content-Type: application/json');

require '../config/db.php';
require '../auth/require_login.php';

$pdo = qa_db();

$user_role  = $_SESSION['role'] ?? '';
$user_name  = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? '');
$role_lower = strtolower($user_role);

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$id      = isset($input['id']) ? (int) $input['id'] : 0;
$remarks = trim($input['remarks'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if ($remarks === '') {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for unflagging.']);
    exit;
}

if (mb_strlen($remarks) > 100) {
    echo json_encode(['success' => false, 'message' => 'Reason must be 100 characters or fewer.']);
    exit;
}

try {
    // regional_manager: the request's branch must be inside their region.
    if ($role_lower === 'regional_manager') {
        $sessionBranches = $_SESSION['user_branches'] ?? [];
        if (!is_array($sessionBranches)) {
            $sessionBranches = explode(',', (string)$sessionBranches);
        }
        $allowedBranches = array_values(array_filter(array_map('trim', $sessionBranches)));

        $chk = $pdo->prepare("SELECT branch FROM dbo.flagging_request WHERE id = ?");
        $chk->execute([$id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        $chk->closeCursor();

        if (!$row) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Request not found.']);
            exit;
        }
        if (!in_array(trim((string)$row['branch']), $allowedBranches, true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not allowed to unflag this request.']);
            exit;
        }
    }

    $stmt = $pdo->prepare("{CALL unflag_flagging_request(?, ?, ?, ?)}");
    $stmt->execute([$id, $user_name, $user_role, $remarks]);

    echo json_encode(['success' => true, 'message' => 'Request unflagged.']);
} catch (Throwable $e) {
    error_log('unflag_request.php: ' . $e->getMessage());

    $knownMessages = [
        'Request not found.',
        'This request is not currently flagged.',
        'You are not allowed to unflag this request.',
        'This request was already unflagged.',
    ];

    foreach ($knownMessages as $known) {
        if (strpos($e->getMessage(), $known) !== false) {
            $statusCode = ($known === 'You are not allowed to unflag this request.') ? 403 : 400;
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'message' => $known]);
            exit;
        }
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to unflag request.',
        // Remove this key once you've diagnosed the error.
        'debug'   => $e->getMessage(),
    ]);
}