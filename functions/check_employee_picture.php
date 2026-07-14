<?php
// ─────────────────────────────────────────────────────────────
// functions/check_employee_picture.php
// Checks whether [dbo].[employee_pictures].[id_picture] already has
// binary image data for the given employee_id.
// Expects GET: ?employee_id=EMP-...
// Returns: { exists: bool, picture_data?: "data:image/...;base64,..." }
// ─────────────────────────────────────────────────────────────

session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$employeeId = trim($_GET['employee_id'] ?? '');

if ($employeeId === '') {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT [id_picture]
        FROM [IPROM_TEST].[dbo].[employee_pictures]
        WHERE [employee_id] = :employee_id
    ");
    $stmt->execute([':employee_id' => $employeeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $binary = $row['id_picture'] ?? null;

    if ($row && $binary !== null && $binary !== '') {
        // Detect the image mime type from the raw bytes so the
        // data URI renders correctly regardless of original format.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_buffer($finfo, $binary) ?: 'image/jpeg';
        finfo_close($finfo);

        $base64 = base64_encode($binary);

        echo json_encode([
            'exists'       => true,
            'picture_data' => "data:{$mime};base64,{$base64}",
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch (Exception $e) {
    echo json_encode([
        'exists'  => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}