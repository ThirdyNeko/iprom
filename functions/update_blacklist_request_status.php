<?php
/**
 * functions/update_blacklist_request_status.php
 *
 * Approves or rejects a pending blacklist request.
 * ASSUMPTION: only admin/super_admin can approve/reject, mirroring the
 * Bulk Verify permission pattern on verification.php. Adjust if
 * audit_manager should also be able to approve.
 */

session_start();
header('Content-Type: application/json');

require '../config/db.php';
require '../auth/require_login.php';

$pdo = qa_db();

$user_role     = $_SESSION['role'] ?? '';
$user_fullname = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Unknown');

if (!in_array(strtolower($user_role), ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not authorized to approve or reject blacklist requests.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$id        = $input['id'] ?? null;
$newStatus = $input['status'] ?? null; // 'Approved' or 'Rejected'

if (!$id || !in_array($newStatus, ['Approved', 'Rejected'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

try {
    $stmt = $pdo->prepare("{CALL update_blacklist_request_status(?, ?, ?)}");
    $stmt->execute([(int) $id, $newStatus, $user_fullname]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (($result['rows_affected'] ?? 0) < 1) {
        echo json_encode(['success' => false, 'message' => 'Request was already actioned or not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => "Request {$newStatus}."]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update request status.']);
}