<?php
/**
 * functions/unflag_request.php
 *
 * Unflags a flagging_request row. There is no approve/reject/cancel
 * anymore — this is the only status-changing action left.
 *
 * Who can unflag (mirrors canUnflagRow() in flagging_request.js —
 * that copy is for showing/hiding the button only, this is the real
 * check):
 *   - the requester themself
 *   - audit_manager, over a request from an audit role (audit_manager
 *     or audit_supervisor)
 *   - admin/super_admin, over a request from a branch_manager
 */

session_start();
header('Content-Type: application/json');

require '../config/db.php';
require '../auth/require_login.php';

$pdo = qa_db();

$user_role  = $_SESSION['role'] ?? '';
$user_name  = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? '');
$role_lower = strtolower($user_role);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = isset($input['id']) ? (int) $input['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, status, requested_by, requested_by_role
        FROM dbo.flagging_request
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    if ($row['status'] !== 'Flagged') {
        echo json_encode(['success' => false, 'message' => 'This request is not currently flagged.']);
        exit;
    }

    $requesterRole = strtolower($row['requested_by_role'] ?? '');

    $isOwner = $row['requested_by'] === $user_name;
    $isAuditManagerOverAudit =
        $role_lower === 'audit_manager'
        && in_array($requesterRole, ['audit_manager', 'audit_supervisor'], true);
    $isAdminOverBranchManager =
        in_array($role_lower, ['admin', 'super_admin'], true)
        && $requesterRole === 'branch_manager';

    if (!($isOwner || $isAuditManagerOverAudit || $isAdminOverBranchManager)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not allowed to unflag this request.']);
        exit;
    }

    $update = $pdo->prepare("
        UPDATE dbo.flagging_request
        SET status = 'Unflagged',
            unflagged_by = ?,
            unflagged_date = GETDATE()
        WHERE id = ? AND status = 'Flagged'
    ");
    $update->execute([$user_name, $id]);

    if ($update->rowCount() === 0) {
        // Someone else unflagged it between our SELECT and UPDATE
        echo json_encode(['success' => false, 'message' => 'This request was already unflagged.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Request unflagged.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to unflag request.']);
}