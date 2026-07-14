<?php
// ─────────────────────────────────────────────────────────────
// functions/upload_employee_picture.php
// Converts the uploaded image to binary and stores it directly in
// [IPROM_TEST].[dbo].[employee_pictures].[id_picture] (VARBINARY column).
// No files are written to disk.
// Expects multipart POST: employee_id, overwrite (0|1), picture (file)
// Returns: { success: bool, message?: string, picture_data?: string }
// ─────────────────────────────────────────────────────────────

session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$employeeId = trim($_POST['employee_id'] ?? '');
$overwrite  = ($_POST['overwrite'] ?? '0') === '1'; // kept for parity/logging; UPDATE handles either case

if ($employeeId === '' || !isset($_FILES['picture'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing employee ID or file.',
    ]);
    exit;
}

$file = $_FILES['picture'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'Upload error.',
    ]);
    exit;
}

// Validate actual file content type (don't trust the client-supplied MIME)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Only JPEG, JPG, or PNG files are allowed.',
    ]);
    exit;
}

// Read the file contents into a binary string
$binaryData = file_get_contents($file['tmp_name']);

if ($binaryData === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to read uploaded file.',
    ]);
    exit;
}

try {
    // Check whether a row already exists for this employee_id
    $check = $pdo->prepare("
        SELECT [id]
        FROM [IPROM_TEST].[dbo].[employee_pictures]
        WHERE [employee_id] = :employee_id
    ");
    $check->execute([':employee_id' => $employeeId]);
    $existingRow = $check->fetch(PDO::FETCH_ASSOC);

    if ($existingRow) {
        // Row exists (whether id_picture was empty or being overwritten) -> UPDATE
        $upd = $pdo->prepare("
            UPDATE [IPROM_TEST].[dbo].[employee_pictures]
            SET [id_picture] = :id_picture
            WHERE [employee_id] = :employee_id
        ");
        // PDO::SQLSRV_ENCODING_BINARY tells the driver this is raw binary
        // data, not text -- without it, PDO_SQLSRV tries to convert the
        // bytes to UCS-2 and throws the "No mapping for the Unicode
        // character exists" error on non-text byte sequences.
        $upd->bindParam(':id_picture', $binaryData, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
        $upd->bindParam(':employee_id', $employeeId);
        $upd->execute();
    } else {
        // No row yet for this employee_id -> INSERT
        $ins = $pdo->prepare("
            INSERT INTO [IPROM_TEST].[dbo].[employee_pictures] ([employee_id], [id_picture])
            VALUES (:employee_id, :id_picture)
        ");
        $ins->bindParam(':employee_id', $employeeId);
        $ins->bindParam(':id_picture', $binaryData, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
        $ins->execute();
    }

    $base64 = base64_encode($binaryData);

    echo json_encode([
        'success'      => true,
        'picture_data' => "data:{$mime};base64,{$base64}",
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}