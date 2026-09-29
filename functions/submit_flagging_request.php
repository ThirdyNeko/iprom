<?php
/**
 * functions/submit_flagging_request.php
 *
 * Inserts a new flagging_request row (status starts as 'Flagged' —
 * there's no approval step, submitting the request IS the flag) plus
 * up to 3 image attachments, sent as multipart/form-data.
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
$role_lower = strtolower($user_role);

// Branch scope: regional_manager -> every branch code in their region
// ($_SESSION['user_branches']); branch_manager -> $_SESSION['branch'] CSV.
if ($role_lower === 'regional_manager') {
    $sessionBranches = $_SESSION['user_branches'] ?? [];
    if (!is_array($sessionBranches)) {
        $sessionBranches = explode(',', (string)$sessionBranches);
    }
    $allowedBranches = array_values(array_filter(array_map('trim', $sessionBranches)));
} else {
    $allowedBranches = array_values(array_filter(
        array_map('trim', explode(',', $_SESSION['branch'] ?? ''))
    ));
}

$is_audit       = in_array($role_lower, ['audit_manager', 'audit_supervisor'], true);
$is_branch_side = in_array($role_lower, ['branch_manager', 'regional_manager'], true);
$can_request_flagging = $is_audit || $is_branch_side;

if (!$can_request_flagging) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not allowed to submit flagging requests.']);
    exit;
}

$employeeId       = nullIfEmpty($_POST['employee_id'] ?? null);
$firstName        = nullIfEmpty($_POST['first_name'] ?? null);
$middleName       = nullIfEmpty($_POST['middle_name'] ?? null);
$lastName         = nullIfEmpty($_POST['last_name'] ?? null);
$suffix           = nullIfEmpty($_POST['suffix'] ?? null);
$dateHired        = nullIfEmpty($_POST['date_hired'] ?? null);
$gender           = nullIfEmpty($_POST['gender'] ?? null);
$maritalStatus    = nullIfEmpty($_POST['marital_status'] ?? null);
$branchCode       = nullIfEmpty($_POST['branch'] ?? null); // branch_code, not the display name
$brand            = nullIfEmpty($_POST['brand'] ?? null);
$employmentStatus = nullIfEmpty($_POST['employment_status'] ?? null);
$subStatus        = nullIfEmpty($_POST['sub_status'] ?? null);
$remarks          = trim($_POST['remarks'] ?? '');

if (!$employeeId || !$firstName || !$lastName || !$branchCode || $remarks === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

if (mb_strlen($remarks) > 100) {
    echo json_encode(['success' => false, 'message' => 'Remarks must be 100 characters or fewer.']);
    exit;
}

// branch_manager / regional_manager can only file for branches in their own
// scope — never trust the client-side dropdown alone.
if ($is_branch_side) {
    if (!in_array(trim($branchCode), $allowedBranches, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $role_lower === 'regional_manager'
                ? 'You can only submit requests for branches in your region.'
                : 'You can only submit requests for your own branch.',
        ]);
        exit;
    }
}

// --- Attachments (max 3 images, 5MB each, png/jpeg) ---
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
    return null; // fileinfo unavailable and mime_content_type unavailable/failed
}

$attachments = [];

if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['tmp_name'])) {
    $count = count($_FILES['attachments']['tmp_name']);

    if ($count > $maxAttachments) {
        echo json_encode(['success' => false, 'message' => 'You can attach up to 3 images.']);
        exit;
    }

    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmpName = $_FILES['attachments']['tmp_name'][$i];
        $size    = $_FILES['attachments']['size'][$i];
        $name    = $_FILES['attachments']['name'][$i];
        $type    = detectMimeType($tmpName); // trust the sniffed type, not the client-sent one

        if ($type === null) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server cannot verify image type (fileinfo extension unavailable).']);
            exit;
        }
        if (!in_array($type, $allowedTypes, true)) {
            echo json_encode(['success' => false, 'message' => "\"$name\" isn't a supported image type."]);
            exit;
        }
        if ($size > $maxSizeBytes) {
            echo json_encode(['success' => false, 'message' => "\"$name\" exceeds 5MB."]);
            exit;
        }

        $data    = file_get_contents($tmpName);
        $dataUri = 'data:' . $type . ';base64,' . base64_encode($data);

        $attachments[] = ['filename' => basename($name), 'picture_data' => $dataUri];
    }
}

try {
    $stmt = $pdo->prepare("{CALL add_flagging_request(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}");
    $stmt->execute([
        $employeeId, $firstName, $middleName, $lastName, $suffix,
        $dateHired, $gender, $maritalStatus, $branchCode, $brand,
        $employmentStatus, $subStatus, $remarks,
        $user_name, $user_role,
    ]);

    $result    = $stmt->fetch(PDO::FETCH_ASSOC);
    $requestId = $result['new_id'] ?? null;
    $stmt->closeCursor();

    if (!$requestId) {
        throw new RuntimeException('Procedure ran but no identity value was returned.');
    }

    if ($attachments) {
        $attStmt = $pdo->prepare("
            INSERT INTO dbo.flagging_request_attachment (flagging_request_id, filename, picture_data, uploaded_date)
            VALUES (?, ?, ?, GETDATE())
        ");
        foreach ($attachments as $att) {
            $attStmt->execute([$requestId, $att['filename'], $att['picture_data']]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Flagging request submitted.']);
} catch (Throwable $e) {
    error_log('submit_flagging_request.php: ' . $e->getMessage());

    if (strpos($e->getMessage(), 'An active flag already exists for this employee') !== false) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This promodiser already has an active flag.',
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit flagging request.',
        // Remove this key once you've diagnosed the error — it's only
        // here so you can see the real cause while wiring this up.
        'debug'   => $e->getMessage(),
    ]);
}