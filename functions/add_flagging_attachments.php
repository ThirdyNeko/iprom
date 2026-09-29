<?php
/**
 * functions/add_flagging_attachments.php
 *
 * Adds image attachments to an EXISTING flagging_request (multipart/form-data:
 * request_id + attachments[]). Rules, all enforced here (the JS only hides
 * buttons):
 *   - request must be 'Flagged'
 *   - existing + new attachments may not exceed 3
 *   - branch_manager / regional_manager: never when last_updated_by_role is
 *     audit_manager / audit_supervisor (audit lock)
 *   - png/jpeg only, 5MB each
 *
 * The count check, inserts and last_updated stamp run in one transaction
 * with the request row locked, so two people uploading at once can't
 * push it past 3.
 */

session_start();
header('Content-Type: application/json');

require '../config/db.php';
require '../auth/require_login.php';

$pdo = qa_db();

$user_role  = $_SESSION['role'] ?? '';
$user_name  = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? '');
$role_lower = strtolower($user_role);

$audit_roles       = ['audit_manager', 'audit_supervisor'];
$branch_side_roles = ['branch_manager', 'regional_manager'];

if (!in_array($role_lower, array_merge($audit_roles, $branch_side_roles), true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not allowed to add attachments.']);
    exit;
}

$maxAttachments = 3;
$maxSizeBytes   = 5 * 1024 * 1024;
$allowedTypes   = ['image/png', 'image/jpeg', 'image/jpg'];

function detectMimeType(string $path): ?string {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type  = $finfo ? finfo_file($finfo, $path) : false;
        if ($finfo) finfo_close($finfo);
        if ($type) return $type;
    }
    if (function_exists('mime_content_type')) {
        $type = mime_content_type($path);
        if ($type) return $type;
    }
    return null;
}

function fail(PDO $pdo, int $code, string $message): void {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$requestId = (int)($_POST['request_id'] ?? 0);
if ($requestId <= 0) {
    fail($pdo, 400, 'Missing request.');
}

if (empty($_FILES['attachments']) || !is_array($_FILES['attachments']['tmp_name'])) {
    fail($pdo, 400, 'No files received.');
}

// --- Validate and encode the uploads first (no DB work yet) ---
$newAttachments = [];
$fileCount = count($_FILES['attachments']['tmp_name']);

if ($fileCount < 1 || $fileCount > $maxAttachments) {
    fail($pdo, 400, "You can add between 1 and $maxAttachments images at a time.");
}

for ($i = 0; $i < $fileCount; $i++) {
    $name = $_FILES['attachments']['name'][$i];

    if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) {
        fail($pdo, 400, "\"$name\" failed to upload.");
    }

    $tmpName = $_FILES['attachments']['tmp_name'][$i];
    $size    = $_FILES['attachments']['size'][$i];
    $type    = detectMimeType($tmpName); // trust the sniffed type, not the client-sent one

    if ($type === null) {
        fail($pdo, 500, 'Server cannot verify image type (fileinfo extension unavailable).');
    }
    if (!in_array($type, $allowedTypes, true)) {
        fail($pdo, 400, "\"$name\" isn't a supported image type.");
    }
    if ($size > $maxSizeBytes) {
        fail($pdo, 400, "\"$name\" exceeds 5MB.");
    }

    $newAttachments[] = [
        'filename'     => basename($name),
        'picture_data' => 'data:' . $type . ';base64,' . base64_encode(file_get_contents($tmpName)),
    ];
}

try {
    $pdo->beginTransaction();

    // Lock the request row so concurrent uploads serialize on it.
    $stmt = $pdo->prepare("
        SELECT status, last_updated_by_role
        FROM dbo.flagging_request WITH (UPDLOCK, HOLDLOCK)
        WHERE id = ?
    ");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$req) {
        fail($pdo, 404, 'Request not found.');
    }
    if ($req['status'] !== 'Flagged') {
        fail($pdo, 409, 'Attachments can only be added to flagged requests.');
    }

    // Audit lock: branch_manager / regional_manager can't add attachments
    // once audit was the last to update the request.
    if (in_array($role_lower, $branch_side_roles, true)
        && in_array(strtolower((string)$req['last_updated_by_role']), $audit_roles, true)) {
        fail($pdo, 403, 'This request was last updated by audit and is locked.');
    }

    // Count what's already attached, THEN check the new files fit
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS c
        FROM dbo.flagging_request_attachment
        WHERE flagging_request_id = ?
    ");
    $stmt->execute([$requestId]);
    $existing = (int)$stmt->fetchColumn();
    $stmt->closeCursor();

    $remaining = $maxAttachments - $existing;
    if ($remaining <= 0) {
        fail($pdo, 409, "This request already has $maxAttachments attachments.");
    }
    if (count($newAttachments) > $remaining) {
        fail($pdo, 409, "You can add $remaining more image" . ($remaining === 1 ? '' : 's') . " to this request.");
    }

    $ins = $pdo->prepare("
        INSERT INTO dbo.flagging_request_attachment (flagging_request_id, filename, picture_data, uploaded_date)
        VALUES (?, ?, ?, GETDATE())
    ");
    foreach ($newAttachments as $att) {
        $ins->execute([$requestId, $att['filename'], $att['picture_data']]);
    }

    $upd = $pdo->prepare("
        UPDATE dbo.flagging_request
        SET last_updated_by      = ?,
            last_updated_by_role = ?,
            last_updated_date    = GETDATE()
        WHERE id = ?
    ");
    $upd->execute([$user_name, $user_role, $requestId]);

    $pdo->commit();

    echo json_encode([
        'success'          => true,
        'attachment_count' => $existing + count($newAttachments),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('add_flagging_attachments.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add attachments.']);
}