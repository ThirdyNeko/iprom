<?php
require_once '../config/db.php';

function autoReactivateEmployees()
{
    $pdo = qa_db();

    // 2. Get affected assignments
    $stmt2 = $pdo->prepare("
        SELECT DISTINCT branch, brand
        FROM employee_info
        WHERE reason_for_update = 'MATERNITY LEAVE'
          AND date_of_return IS NOT NULL
          AND CAST(date_of_return AS DATE) <= CAST(GETDATE() AS DATE)
    ");
    $stmt2->execute();
    $assignments = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $history1 = $pdo->prepare("
        INSERT INTO employee_reason_history (
            employee_id,
            reason_for_update,
            update_date
        )
        SELECT 
            employee_id,
            'AUTO REACTIVATED (MATERNITY RETURN)',
            GETDATE()
        FROM employee_info
        WHERE reason_for_update = 'MATERNITY LEAVE'
        AND date_of_return IS NOT NULL
        AND hidden = true
        AND CAST(date_of_return AS DATE) <= CAST(GETDATE() AS DATE)
    ");
    $history1->execute();

    // 1. Reactivate returning employees
    $stmt1 = $pdo->prepare("
        UPDATE employee_info
        SET status = 'ACTIVE',
            last_updated_by = 'SYSTEM',
            hidden = false,
            date_separated = NULL,
            date_of_return = NULL,
            updated_at = GETDATE()
        WHERE reason_for_update = 'MATERNITY LEAVE'
          AND date_of_return IS NOT NULL
          AND CAST(date_of_return AS DATE) <= CAST(GETDATE() AS DATE)
          AND status = 'INACTIVE'
    ");
    $stmt1->execute();

    foreach ($assignments as $a) {

        // 3. Check assignment capacity
        $check = $pdo->prepare("
            SELECT assigned_count, required_count
            FROM assignment
            WHERE branch_name = :branch
              AND brand_name = :brand
        ");

        $check->execute([
            ':branch' => $a['branch'],
            ':brand'  => $a['brand']
        ]);

        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row) continue;

        // 4. Only remove reliever if FULL
        // Only remove if FULL or OVER
        if ((int)$row['assigned_count'] >= (int)$row['required_count']) {

            $sql = "
            WITH Ranked AS (
                SELECT TOP 1
                    id,
                    employee_id,
                    COUNT(*) OVER (PARTITION BY employee_id) AS emp_count
                FROM employee_info
                WHERE branch = :branch
                AND brand = :brand
                AND employment_status IN ('RELIEVER', 'SEASONAL')
                AND status = 'ACTIVE'
                ORDER BY 
                    CASE 
                        WHEN end_date IS NOT NULL AND end_date <= GETDATE() THEN 0
                        ELSE 1
                    END,
                    CASE 
                        WHEN end_date IS NULL THEN 999999
                        ELSE ABS(DATEDIFF(DAY, end_date, GETDATE()))
                    END,
                    id ASC
            )

            -- DELETE duplicates
            DELETE ei
            FROM employee_info ei
            JOIN Ranked r ON ei.id = r.id
            WHERE r.emp_count > 1;

            -- UPDATE single
            UPDATE ei
            SET status = 'INACTIVE',
                last_updated_by = 'SYSTEM',
                updated_at = GETDATE()
            FROM employee_info ei
            JOIN Ranked r ON ei.id = r.id
            WHERE r.emp_count = 1;
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':branch' => $a['branch'],
                ':brand'  => $a['brand']
            ]);

            $history = $pdo->prepare("
                INSERT INTO employee_reason_history (
                    employee_id,
                    reason_for_update,
                    update_date
                )
                VALUES (
                    :employee_id,
                    'AUTO REMOVED (CAPACITY CONTROL - RELIEVER/SEASONAL)',
                    GETDATE()
                )
            ");

            $history->execute([':id' => $id]);
        }
    }
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
          AND reason_for_update = 'MATERNITY LEAVE'
    ");

    $stmt->execute();

    $stmt2 = $pdo->prepare("
        WITH Duplicates AS (
            SELECT *,
                COUNT(*) OVER (
                    PARTITION BY employee_id, branch, brand
                ) AS emp_count
            FROM employee_info
        )
        UPDATE Duplicates
        SET hidden = 1
        WHERE emp_count > 1;
    ");

    $stmt2->execute();

    $stmt3 = $pdo->prepare("
        UPDATE employee_info
        SET status = 'INACTIVE',
            last_updated_by = 'SYSTEM',
            updated_at = GETDATE()
        WHERE date_separated IS NOT NULL
          AND CAST(date_separated AS DATE) <= CAST(GETDATE() AS DATE)
          AND status = 'ACTIVE'
    ");

    $stmt3->execute();

    $stmt4 = $pdo->prepare("
        WITH Duplicates AS (
            SELECT *,
                ROW_NUMBER() OVER (
                    PARTITION BY employee_id, branch, brand
                    ORDER BY updated_at DESC
                ) AS rn
            FROM employee_info
        )
        DELETE FROM Duplicates
        WHERE rn > 1;
    ");

    $stmt4->execute();

    $history = $pdo->prepare("
        INSERT INTO employee_reason_history (
            employee_id,
            reason_for_update,
            update_date
        )
        SELECT 
            employee_id,
            'AUTO DEACTIVATED (DATE SEPARATED)',
            GETDATE()
        FROM employee_info
        WHERE date_separated IS NOT NULL
        AND hidden = false
        AND CAST(date_separated AS DATE) <= CAST(GETDATE() AS DATE)
        AND status = 'INACTIVE'
    ");
    $history->execute();
}

function autoActivateSeasonal()
{
    $pdo = qa_db();

    $stmt = $pdo->prepare("
        UPDATE ei
        SET ei.status = 'ACTIVE',
            ei.last_updated_by = 'SYSTEM',
            ei.updated_at = GETDATE(),
            ei.hidden = false
        FROM employee_info ei
        INNER JOIN assignment a
            ON a.branch_name = ei.branch
           AND a.brand_name  = ei.brand
        WHERE ei.date_separated IS NULL
          AND CAST(ei.start_date AS DATE) <= CAST(GETDATE() AS DATE)
          AND ei.employment_status IN ('SEASONAL', 'RELIEVER')
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

    $history = $pdo->prepare("
        INSERT INTO employee_reason_history (
            employee_id,
            reason_for_update,
            update_date
        )
        SELECT 
            ei.employee_id,
            'AUTO REACTIVATED (SEASONAL START)',
            GETDATE()
        FROM employee_info ei
        INNER JOIN assignment a
            ON a.branch_name = ei.branch
        AND a.brand_name  = ei.brand
        WHERE ei.date_separated IS NULL
        AND CAST(ei.start_date AS DATE) <= CAST(GETDATE() AS DATE)
        AND ei.employment_status IN ('SEASONAL', 'RELIEVER')
        AND ei.status = 'ACTIVE'
        AND NOT EXISTS (
                SELECT 1 
                FROM employee_reason_history h
                WHERE h.employee_id = ei.employee_id
                AND h.reason_for_update = 'AUTO REACTIVATED (SEASONAL START)'
        );
    ");
    $history->execute();
}

function autoDeactivateSeasonal()
{
    $pdo = qa_db();

    // =========================
    // 1. GET AFFECTED EMPLOYEES
    // =========================
    $stmt = $pdo->prepare("
        SELECT *
        FROM employee_info
        WHERE date_separated IS NULL
          AND employment_status IN ('SEASONAL', 'RELIEVER')
          AND CAST(end_date AS DATE) <= CAST(GETDATE() AS DATE)
          AND status = 'ACTIVE'
    ");

    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($employees as $emp) {

        $employee_id = $emp['employee_id'];

        // =========================
        // 2. CHECK MULTI STATUS
        // =========================
        $sub = strtoupper($emp['sub_status']);
        $isMulti = in_array($sub, ['MULTI BRAND', 'MULTI BRANCH']);

        if ($isMulti) {

            // =========================
            // 3. GET ALL SAME EMPLOYEE_ID GROUP
            // =========================
            $groupStmt = $pdo->prepare("
                SELECT id
                FROM employee_info
                WHERE employee_id = :employee_id
            ");

            $groupStmt->execute([
                ':employee_id' => $employee_id
            ]);

            $group = $groupStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($group)) {

                // sort to pick survivor (lowest id = oldest)
                sort($group);
                $survivor = array_shift($group);

                // =========================
                // 4. DELETE ALL OTHERS
                // =========================
                if (!empty($group)) {

                    $in = implode(',', array_fill(0, count($group), '?'));

                    $deleteStmt = $pdo->prepare("
                        DELETE FROM employee_info
                        WHERE id IN ($in)
                    ");

                    $deleteStmt->execute($group);
                }

                // =========================
                // 5. UPDATE SURVIVOR
                // =========================
                $pdo->prepare("
                    UPDATE employee_info
                    SET status = 'INACTIVE',
                        date_separated = GETDATE(),
                        reason_for_update = 'END OF CONTRACT (SURVIVOR)',
                        last_updated_by = 'SYSTEM',
                        updated_at = GETDATE()
                    WHERE id = ?
                ")->execute([$survivor]);

                // =========================
                // 6. HISTORY LOG (SURVIVOR ONLY)
                // =========================
                $pdo->prepare("
                    INSERT INTO employee_reason_history (
                        employee_id,
                        reason_for_update,
                        update_date
                    )
                    VALUES (?, 'AUTO DEACTIVATED (END OF CONTRACT)', GETDATE())
                ")->execute([$employee_id]);
            }

        } else {

            // =========================
            // NORMAL SEASONAL FLOW
            // =========================
            $pdo->prepare("
                UPDATE employee_info
                SET status = 'INACTIVE',
                    date_separated = GETDATE(),
                    reason_for_update = 'END OF CONTRACT',
                    last_updated_by = 'SYSTEM',
                    updated_at = GETDATE()
                WHERE id = ?
            ")->execute([$emp['id']]);

            // =========================
            // HISTORY LOG
            // =========================
            $pdo->prepare("
                INSERT INTO employee_reason_history (
                    employee_id,
                    reason_for_update,
                    update_date
                )
                VALUES (?, 'AUTO DEACTIVATED (END OF CONTRACT)', GETDATE())
            ")->execute([$employee_id]);
        }
    }
}