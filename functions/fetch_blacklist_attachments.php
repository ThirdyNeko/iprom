<?php
// ─────────────────────────────────────────────────────────────
// functions/fetch_blacklist_attachments.php
// Returns all image attachments for a given blacklist_request_id,
// each as a base64 data URI — same response shape as
// upload_employee_picture.php's picture_data field.
//
// Access rules (must mirror canViewAttachments() in JS — client-side
// check there is for showing/hiding the button only):
//   - the requester themself
//   - the literal 'admin' or 'super_admin' role — can view all
//   - an audit_manager viewing a request submitted by an audit_supervisor
// ─────────────────────────────────────────────────────────────

session_start();
header('Content-Type: application/json');
require '../config/db.php';
require '../auth/require_login.php';

$pdo = qa_db();

$requestId = trim($_GET['request_id'] ?? '');

if ($requestId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing request_id.']);
    exit;
}

$user_role     = strtolower($_SESSION['role'] ?? '');
$user_fullname = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? '');

$admin_roles = ['admin', 'super_admin'];

try {
    $stmt = $pdo->prepare("
        SELECT [requested_by], [requester_role]
        FROM blacklist_request
        WHERE [id] = :request_id
    ");
    $stmt->execute([':request_id' => $requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    $isOwner = $request['requested_by'] === $user_fullname;
    $isAdmin = in_array($user_role, $admin_roles, true);
    $isManagerOverSupervisor =
        $user_role === 'audit_manager'
        && strtolower($request['requester_role'] ?? '') === 'audit_supervisor';

    if (!$isOwner && !$isAdmin && !$isManagerOverSupervisor) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not authorized to view these attachments.']);
        exit;
    }

    $attStmt = $pdo->prepare("
        SELECT [attachment], [mime_type], [original_filename]
        FROM blacklist_request_attachment
        WHERE [blacklist_request_id] = :request_id
        ORDER BY [id]
    ");
    $attStmt->execute([':request_id' => $requestId]);

    $results = [];
    while ($row = $attStmt->fetch(PDO::FETCH_ASSOC)) {
        $base64 = base64_encode($row['attachment']);
        $results[] = [
            'picture_data' => "data:{$row['mime_type']};base64,{$base64}",
            'filename'     => $row['original_filename'],
        ];
    }

    echo json_encode(['success' => true, 'attachments' => $results]);
} catch (PDOException $e) {
    error_log('fetch_blacklist_attachments failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load attachments.']);
}