<?php
session_start();
header('Content-Type: application/json');

include '../config/db.php';
$pdo = qa_db();

$updated_by = $_SESSION['username'] ?? 'system';

// Accept JSON input
$data = json_decode(file_get_contents("php://input"), true);

$branch = $data['branch'] ?? '';
$brand  = $data['brand'] ?? '';
$required = isset($data['required']) ? (int)$data['required'] : -1;

if (!$branch || !$brand || $required < 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid input"
    ]);
    exit;
}

try {

    $pdo->beginTransaction();

    // ✅ GET ASSIGNED COUNT
    $stmt = $pdo->prepare("
        SELECT assigned_count
        FROM assignment
        WHERE branch_name = ? AND brand_name = ?
    ");
    $stmt->execute([$branch, $brand]);
    $assigned = $stmt->fetchColumn();

    if ($assigned === false) {
        echo json_encode([
            "status" => "error",
            "message" => "Assignment record not found"
        ]);
        exit;
    }

    $assigned = (int)$assigned;

    // =====================================================
    // 🚨 SPECIAL CASE: REQUIRED = 0 → PULL OUT ALL
    // =====================================================
    if ($required === 0) {

        // Get all active employees
        $stmt = $pdo->prepare("
            SELECT id, employee_id, sub_status, roving_group_id, multi_brand_group_id
            FROM employee_info
            WHERE branch = ?
            AND brand = ?
            AND status = 'ACTIVE'
        ");
        $stmt->execute([$branch, $brand]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($employees as $emp) {

            $empId = $emp['id'];
            $employeeId = $emp['employee_id'];
            $subStatus = strtoupper(trim($emp['sub_status'] ?? ''));

            $shouldDelete = false;

            // ============================================
            // MULTI BRANCH CHECK
            // ============================================
            if ($subStatus === 'MULTI BRANCH' && !empty($emp['roving_group_id'])) {

                $stmtGroup = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM employee_info 
                    WHERE roving_group_id = ?
                ");
                $stmtGroup->execute([$emp['roving_group_id']]);
                $count = (int)$stmtGroup->fetchColumn();

                if ($count > 1) {
                    $shouldDelete = true;
                }
            }

            // ============================================
            // MULTI BRAND CHECK
            // ============================================
            elseif ($subStatus === 'MULTI BRAND' && !empty($emp['multi_brand_group_id'])) {

                $stmtGroup = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM employee_info 
                    WHERE multi_brand_group_id = ?
                ");
                $stmtGroup->execute([$emp['multi_brand_group_id']]);
                $count = (int)$stmtGroup->fetchColumn();

                if ($count > 1) {
                    $shouldDelete = true;
                }
            }

            // ============================================
            // DELETE FLOW
            // ============================================
            if ($shouldDelete) {

                // 1. INSERT HISTORY
                $stmtHist = $pdo->prepare("
                    INSERT INTO employee_reason_history
                    (employee_id, reason_for_update, update_date, remarks)
                    VALUES (?, ?, GETDATE(), ?)
                ");

                $stmtHist->execute([
                    $employeeId,
                    'PULL-OUT / TERMINATED',
                    "Auto pull-out (required = 0 | Branch: $branch | Brand: $brand)"
                ]);

                // 2. DELETE EMPLOYEE
                $stmtDelete = $pdo->prepare("
                    DELETE FROM employee_info
                    WHERE id = ?
                ");

                $stmtDelete->execute([$empId]);

            } else {

                // 1. INSERT HISTORY (INACTIVE CASE)
                $stmtHist = $pdo->prepare("
                    INSERT INTO employee_reason_history
                    (employee_id, reason_for_update, update_date, remarks)
                    VALUES (?, ?, GETDATE(), ?)
                ");

                $stmtHist->execute([
                    $employeeId,
                    'PULL-OUT / TERMINATED',
                    "Auto inactivated (required = 0 | Branch: $branch | Brand: $brand)"
                ]);

                // 2. UPDATE EMPLOYEE TO INACTIVE
                $stmtUpdate = $pdo->prepare("
                    UPDATE employee_info
                    SET 
                        status = 'INACTIVE',
                        reason_for_update = 'PULL-OUT / TERMINATED',
                        sub_status = 'STATIONARY',
                        employment_status = 'PERMANENT',
                        updated_at = GETDATE(),
                        last_updated_by = ?
                    WHERE id = ?
                ");

                $stmtUpdate->execute([$updated_by, $empId]);
            }
        }
    }

    // =====================================================
    // 🚨 NORMAL RULE (ONLY IF REQUIRED > 0)
    // =====================================================
    if ($required > 0 && $required < $assigned) {
        echo json_encode([
            "status" => "error",
            "message" => "Plantilla ($required) cannot be less than assigned ($assigned). Please reassign or remove promodizers first."
        ]);
        $pdo->rollBack();
        exit;
    }

    // =====================================================
    // ✅ UPDATE ASSIGNMENT
    // =====================================================
    $stmt = $pdo->prepare("
        UPDATE assignment
        SET required_count = ?,
            updated_at = GETDATE(),
            updated_by = ?
        WHERE branch_name = ? AND brand_name = ?
    ");

    $ok = $stmt->execute([$required, $updated_by, $branch, $brand]);

    $pdo->commit();

    echo json_encode([
        "status" => $ok ? "success" : "error",
        "message" => $ok ? "Updated successfully" : "Update failed"
    ]);

} catch (Exception $e) {

    $pdo->rollBack();

    echo json_encode([
        "status" => "error",
        "message" => "Server error"
    ]);
}