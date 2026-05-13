<?php
require '../config/db.php';
session_start();

$pdo = qa_db();

$employee = $_POST['employee_id'] ?? null;
$user = $_SESSION['user_id'] ?? null;

if (!$employee) {
    exit("Missing employee");
}

try {
    $pdo->beginTransaction();

    // 1. Restore history from ORIGINAL SOURCE (FIXED)
    $stmt = $pdo->prepare("
        UPDATE employee_reason_history
        SET employee_id = original_employee_id
        WHERE original_employee_id = ?
    ");
    $stmt->execute([$employee]);

    // 2. Unhide employee
    $stmt = $pdo->prepare("
        UPDATE employee_info
        SET hidden = 0,
            status = 'ACTIVE'
        WHERE employee_id = ?
    ");
    $stmt->execute([$employee]);

    // 3. Mark log (optional, safe even if imperfect)
    $stmt = $pdo->prepare("
        UPDATE employee_merge_log
        SET unmerged_at = GETDATE(),
            unmerged_by = ?
        WHERE merged_employee_id = ?
          AND unmerged_at IS NULL
    ");
    $stmt->execute([$user, $employee]);

    $pdo->commit();

    echo "Unmerge successful";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}