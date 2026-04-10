<?php
require_once '../config/db.php';

function autoReactivateEmployees()
{
    $pdo = qa_db();

    $stmt = $pdo->prepare("
        UPDATE employee_info
        SET status = 'ACTIVE',
            updated_at = GETDATE()
        WHERE employment_status = 'MATERNITY LEAVE'
          AND date_of_return IS NOT NULL
          AND CAST(date_of_return AS DATE) <= CAST(GETDATE() AS DATE)
          AND status = 'INACTIVE'
    ");

    $stmt->execute();
}

function autoDeactivateEmployees()
{
    $pdo = qa_db();

    $stmt = $pdo->prepare("
        UPDATE employee_info
        SET status = 'INACTIVE',
            updated_at = GETDATE()
        WHERE date_separated IS NOT NULL
          AND CAST(date_separated AS DATE) <= CAST(GETDATE() AS DATE)
          AND status = 'ACTIVE'
    ");

    $stmt->execute();
}