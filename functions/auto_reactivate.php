<?php
require_once '../config/db.php';

function autoReactivateEmployees()
{
    $pdo = qa_db();

    $stmt = $pdo->prepare("
        UPDATE employee_info
        SET status = 'ACTIVE',
            last_updated_by = 'SYSTEM',
            updated_at = GETDATE()
        WHERE reason_for_update = 'MATERNITY LEAVE'
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
            last_updated_by = 'SYSTEM',
            updated_at = GETDATE()
        WHERE date_separated IS NOT NULL
          AND CAST(date_separated AS DATE) <= CAST(GETDATE() AS DATE)
          AND status = 'ACTIVE'
    ");

    $stmt->execute();
}

function autoActivateSeasonal()
{
    $pdo = qa_db();

    $stmt = $pdo->prepare("
        UPDATE ei
        SET ei.status = 'ACTIVE',
            ei.last_updated_by = 'SYSTEM',
            ei.updated_at = GETDATE()
        FROM employee_info ei
        INNER JOIN assignment a
            ON a.branch_name = ei.branch
           AND a.brand_name  = ei.brand
        WHERE ei.date_separated IS NULL
          AND CAST(ei.start_date AS DATE) <= CAST(GETDATE() AS DATE)
          AND ei.sub_status IN ('SEASONAL', 'RELIEVER')
          AND ei.status = 'INACTIVE'
          AND (
                SELECT COUNT(*)
                FROM employee_info ei2
                WHERE ei2.branch = ei.branch
                  AND ei2.brand  = ei.brand
                  AND ei2.status = 'ACTIVE'
          ) < a.required_count
    ");

    $stmt->execute();
}

function autoDeactivateSeasonal()
{
    $pdo = qa_db();

    $stmt = $pdo->prepare("
        UPDATE employee_info
        SET status = 'INACTIVE',
            last_updated_by = 'SYSTEM',
            updated_at = GETDATE(),
            date_separated = GETDATE(),
            reason_for_update = 'END OF CONTRACT'
        WHERE date_separated IS NULL
          AND sub_status IN ('SEASONAL', 'RELIEVER')
          AND CAST(end_date AS DATE) <= CAST(GETDATE() AS DATE)
          AND status = 'ACTIVE'
    ");

    $stmt->execute();
}