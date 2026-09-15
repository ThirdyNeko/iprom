<?php
/**
 * functions/unflag_request.php
 *
 * Unflags a flagging_request row via dbo.unflag_flagging_request, which
 * does the permission check, status check, and race-condition guard
 * atomically server-side. There is no approve/reject/cancel anymore —
 * this is the only status-changing action left.
 */

session_start();
header('Content-Type: application/json');

require '../config/db.php';
require '../auth/require_login.php';

if (!function_exists('nullIfEmpty')) {
    function nullIfEmpty($value) {
        return ($value === null || $value === '') ? null : $value;
    }
}

$pdo = qa_db();

$user_role  = $_SESSION['role'] ?? '';
$user_name  = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? '');

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