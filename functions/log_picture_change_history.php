<?php
// ─────────────────────────────────────────────────────────────
// functions/log_picture_change_history.php
// Logs a "CHANGE EMPLOYEE PICTURE" entry to employee_reason_history.
// Kept separate from upload_employee_picture.php on purpose --
// that endpoint is shared by other flows (e.g. LOA verification)
// where a picture upload does NOT mean "reason for update: change
// picture". This endpoint is only called from the edit_promodizer
// page's Change Picture modal, right after a successful upload.
// Expects POST: employee_id
// Returns: { success: bool, message?: string }
// ─────────────────────────────────────────────────────────────

session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$employeeId = trim($_POST['employee_id'] ?? '');

if ($employeeId === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing employee ID.',
    ]);
    exit;
}

// TODO: confirm this matches the actual session key used for
// last_updated_by in update_promodizer.php.
$updatedBy = $_SESSION['username'] ?? $_SESSION['name'] ?? $_SESSION['user'] ?? 'SYSTEM';
$historyMsg = 'CHANGE EMPLOYEE PICTURE | Updated ID Picture';

try {
    $stmt = $pdo->prepare("
        INSERT INTO employee_reason_history
            (employee_id, reason_for_update, update_date, updated_by, remarks)
        VALUES
            (:employee_id, :reason_for_update, GETDATE(), :updated_by, :remarks)
    ");
    $stmt->execute([
        ':employee_id'       => $employeeId,
        ':reason_for_update' => $historyMsg,
        ':updated_by'        => $updatedBy,
        ':remarks'           => '',
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}