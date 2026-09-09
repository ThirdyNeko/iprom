<?php
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

$allowed_roles = ['audit_manager', 'audit_supervisor', 'branch_manager'];

if (!in_array(strtolower($user_role), $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not authorized to submit blacklist requests.']);
    exit;
}

$input = $_POST; // multipart now, not JSON

$required = ['first_name', 'last_name', 'branch', 'employee_id'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
        exit;
    }
}

// ---------------------------------------------------------------
// Validate attachments (0–3 images) BEFORE touching the database.
// Same finfo-based real-content-type check as upload_employee_picture.php
// -- never trust the client-supplied MIME type.
// ---------------------------------------------------------------
const MAX_ATTACHMENTS   = 3;
const MAX_ATTACHMENT_MB = 5;
$allowedMime = ['image/jpeg', 'image/png']; // matches upload_employee_picture.php; add image/webp here too if you want to allow it

$attachments = []; // [['data' => binary, 'mime' => ..., 'name' => ...], ...]

if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['tmp_name'])) {
    $count = count($_FILES['attachments']['tmp_name']);

    if ($count > MAX_ATTACHMENTS) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'You can attach up to ' . MAX_ATTACHMENTS . ' images.']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) {
            finfo_close($finfo);
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'One or more files failed to upload.']);
            exit;
        }

        $tmpPath  = $_FILES['attachments']['tmp_name'][$i];
        $size     = $_FILES['attachments']['size'][$i];
        $origName = $_FILES['attachments']['name'][$i];

        if ($size > MAX_ATTACHMENT_MB * 1024 * 1024) {
            finfo_close($finfo);
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => "\"{$origName}\" exceeds " . MAX_ATTACHMENT_MB . "MB."]);
            exit;
        }

        $mime = finfo_file($finfo, $tmpPath);
        if (!in_array($mime, $allowedMime, true)) {
            finfo_close($finfo);
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => "\"{$origName}\" must be JPEG or PNG."]);
            exit;
        }

        $binaryData = file_get_contents($tmpPath);
        if ($binaryData === false) {
            finfo_close($finfo);
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => "Failed to read \"{$origName}\"."]);
            exit;
        }

        $attachments[] = ['data' => $binaryData, 'mime' => $mime, 'name' => $origName];
    }

    finfo_close($finfo);
}

try {
    $stmt = $pdo->prepare("{CALL add_blacklist_request(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}");
    $stmt->execute([
        $input['first_name'],
        nullIfEmpty($input['middle_name'] ?? null),
        $input['last_name'],
        nullIfEmpty($input['birthday'] ?? null),
        nullIfEmpty($input['suffix'] ?? null),
        nullIfEmpty($input['gender'] ?? null),
        nullIfEmpty($input['marital_status'] ?? null),
        $input['branch'],
        nullIfEmpty($input['brand'] ?? null),
        nullIfEmpty($input['employment_status'] ?? null),
        nullIfEmpty($input['end_date'] ?? null),
        nullIfEmpty($input['remarks'] ?? null),
        $input['employee_id'],
        $user_fullname,
        strtolower($user_role),
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $newId  = $result['new_id'] ?? null;

    // ---------------------------------------------------------------
    // Persist attachments as VARBINARY(MAX), same bind pattern as
    // upload_employee_picture.php
    // ---------------------------------------------------------------
    if ($newId && !empty($attachments)) {
        $insertAttachment = $pdo->prepare("
            INSERT INTO blacklist_request_attachment
                ([blacklist_request_id], [attachment], [mime_type], [original_filename])
            VALUES (:request_id, :attachment, :mime_type, :original_filename)
        ");

        foreach ($attachments as $att) {
            $requestId = $newId;
            $mime      = $att['mime'];
            $origName  = $att['name'];

            $insertAttachment->bindParam(':request_id', $requestId);
            $insertAttachment->bindParam(':attachment', $att['data'], PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
            $insertAttachment->bindParam(':mime_type', $mime);
            $insertAttachment->bindParam(':original_filename', $origName);
            $insertAttachment->execute();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Blacklist request submitted successfully.',
        'new_id'  => $newId,
    ]);
} catch (PDOException $e) {
    error_log('add_blacklist_request failed: ' . $e->getMessage());
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'A pending blacklist request already exists for this employee, or the request could not be saved.',
        'debug'   => $e->getMessage(),
    ]);
    exit;
}