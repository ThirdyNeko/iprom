<?php
/**
 * functions/cancel_blacklist_request.php
 *
 * Cancels a pending blacklist request. Two people can do this:
 * - The original requester, cancelling their own pending request.
 * - An audit_manager, cancelling a pending request originally submitted
 *   by an audit_supervisor.
 *
 * Authorization is enforced in cancel_blacklist_request (SQL), not just
 * here — this endpoint just passes the actor's identity/role through and
 * relays whatever the DB decided. Never trust client-side button
 * visibility alone for a write path.
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

$user_role     = $_SESSION['role'] ?? '';
$user_fullname = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Unknown');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id    = $input['id'] ?? null;

if (!$id) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

try {
    $stmt = $pdo->prepare("{CALL cancel_blacklist_request(?, ?, ?)}");
    $stmt->execute([(int) $id, $user_fullname, strtolower($user_role)]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $rowsAffected = (int) ($result['rows_affected'] ?? 0);
    $reason       = $result['reason'] ?? null;

    if ($rowsAffected < 1) {
        $messages = [
            'not_found'      => 'Request not found.',
            'not_pending'    => 'Only pending requests can be cancelled.',
            'not_authorized' => 'You are not authorized to cancel this request.',
        ];
        echo json_encode([
            'success' => false,
            'message' => $messages[$reason] ?? 'Unable to cancel this request.',
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Request cancelled.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to cancel request.']);
}