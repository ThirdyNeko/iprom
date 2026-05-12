<?php
require '../config/db.php';
session_start();

$pdo = qa_db();

$primary = $_POST['primary_employee'] ?? null;
$secondary = $_POST['secondary_employee'] ?? null;

if (!$primary || !$secondary) {
    exit("Missing data");
}

if ($primary == $secondary) {
    exit("Cannot merge same employee");
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE employee_reason_history
        SET original_employee_id = employee_id,
            employee_id = ?
        WHERE employee_id = ?
    ");
    $stmt->execute([$primary, $secondary]);

    $stmt = $pdo->prepare("
        UPDATE employee_info
        SET 
        hidden = 1,
        status = 'INACTIVE',
        start_date = NULL,
        end_date = NULL

        WHERE employee_id = ?
    ");
    $stmt->execute([$secondary]);

    $stmt = $pdo->prepare("
        INSERT INTO employee_merge_log
        (primary_employee_id, merged_employee_id, merged_by)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$primary, $secondary, $_SESSION['user_id'] ?? null]);

    $pdo->commit();

    echo "Merge successful";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}