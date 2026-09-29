<?php
/**
 * functions/fetch_flagging_attachments.php
 *
 * Returns the attachments for one flagging_request row, as data URIs
 * ready to drop straight into an <img src>.
 *
 * Who can view attachments (mirrors canViewAttachments() in
 * flagging_request.js — that copy is for showing/hiding the button
 * only, this is the real check):
 *   - the requester themself
 *   - admin / super_admin
 *   - audit_manager / audit_supervisor
 */

session_start();
header('Content-Type: application/json');

require '../config/db.php';
require '../auth/require_login.php';

$pdo = qa_db();

$user_role  = $_SESSION['role'] ?? '';
$user_name  = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? '');
$role_lower = strtolower($user_role);

$requestId = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0;

if ($requestId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, requested_by
        FROM dbo.flagging_request
        WHERE id = ?
    ");
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    $isOwner = $row['requested_by'] === $user_name;
    $isAdmin = in_array($role_lower, ['admin', 'super_admin'], true);
    $isAudit = in_array($role_lower, ['audit_manager', 'audit_supervisor'], true);

    if (!($isOwner || $isAdmin || $isAudit)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not allowed to view these attachments.']);
        exit;
    }

    $attStmt = $pdo->prepare("
        SELECT filename, picture_data
        FROM dbo.flagging_request_attachment
        WHERE flagging_request_id = ?
        ORDER BY id
    ");
    $attStmt->execute([$requestId]);
    $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'attachments' => $attachments]);
} catch (Throwable $e) {
    error_log('fetch_flagging_attachments.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load attachments.']);
}