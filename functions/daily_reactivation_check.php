<?php
require_once '../config/db.php';
require_once 'auto_reactivate.php';

$pdo = qa_db();

$today = date('Y-m-d');

// check last run
$stmt = $pdo->prepare("
    SELECT flag_value 
    FROM system_flags 
    WHERE flag_name = 'auto_reactivate_date'
");
$stmt->execute();
$lastRun = $stmt->fetchColumn();

if ($lastRun !== $today) {

    // run update
    autoDeactivateSeasonal();
    autoActivateSeasonal();
    autoDeactivateEmployees();
    autoReactivateEmployees();


    // update flag
    if ($lastRun === false) {
        $insert = $pdo->prepare("
            INSERT INTO system_flags (flag_name, flag_value)
            VALUES ('auto_reactivate_date', :today)
        ");
        $insert->execute([':today' => $today]);
    } else {
        $update = $pdo->prepare("
            UPDATE system_flags
            SET flag_value = :today,
                updated_at = GETDATE()
            WHERE flag_name = 'auto_reactivate_date'
        ");
        $update->execute([':today' => $today]);
    }
}