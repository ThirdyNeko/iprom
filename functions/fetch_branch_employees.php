<?php
/**
 * functions/fetch_branch_employees.php
 *
 * Returns employees for a given branch, to populate the Brand/Promodiser
 * dropdowns in the Request Flagging modal after a branch is chosen.
 *
 * Server-side branch enforcement:
 * - branch_manager: locked to their own session branch(es).
 * - regional_manager: locked to the branch codes in $_SESSION['user_branches']
 *   (their region, resolved at login).
 * - audit_manager / audit_supervisor / admin / super_admin: unrestricted.
 */

session_start();
header('Content-Type: application/json');

require '../config/db.php';
require '../auth/require_login.php';

$pdo = qa_db();

$user_role = strtolower($_SESSION['role'] ?? '');

// Branch scope: regional_manager has a list of codes (their region),
// everyone else has a comma-delimited string in $_SESSION['branch'].
if ($user_role === 'regional_manager') {
    $sessionBranches = $_SESSION['user_branches'] ?? [];
    if (!is_array($sessionBranches)) {
        $sessionBranches = explode(',', (string)$sessionBranches);
    }
    $allowed_branches = array_values(array_filter(array_map('trim', $sessionBranches)));
} else {
    $allowed_branches = array_values(array_filter(
        array_map('trim', explode(',', $_SESSION['branch'] ?? ''))
    ));
}

$requested_branch = trim($_GET['branch'] ?? '');

if ($requested_branch === '') {
    echo json_encode([]);
    exit;
}

if (!in_array($user_role, ['admin', 'super_admin', 'audit_manager', 'audit_supervisor'], true)) {
    // branch_manager / regional_manager (or any other restricted role):
    // only branches inside their own scope.
    if (!in_array($requested_branch, $allowed_branches, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not authorized to view employees for that branch.']);
        exit;
    }
}

$stmt = $pdo->prepare(
    "SELECT
        [id],
        [employee_id],
        [first_name],
        [middle_name],
        [last_name],
        [birthday],
        [date_hired],
        [suffix],
        [gender],
        [marital_status],
        [branch_code],
        [branch],
        [brand],
        [employment_status],
        [sub_status]
     FROM (
        SELECT
            ei.[id],
            ei.[employee_id],
            ei.[first_name],
            ei.[middle_name],
            ei.[last_name],
            CONVERT(VARCHAR(10), ei.[birthday], 101) AS [birthday],
            ei.[date_hired],
            ei.[suffix],
            ei.[gender],
            ei.[marital_status],
            ei.[branch] AS [branch_code],
            COALESCE(b.[branch], ei.[branch]) AS [branch],
            ei.[brand],
            ei.[employment_status],
            ei.[sub_status],
            ROW_NUMBER() OVER (
                PARTITION BY ei.[employee_id]
                ORDER BY ei.[id]
            ) AS [rn]
        FROM dbo.[employee_info] ei
        LEFT JOIN dbo.[branches] b ON b.[branch_code] = ei.[branch]
        WHERE ei.[branch] = :branch
            AND (
                ei.[reason_for_update] NOT IN ('Clerical Error', 'BLACKLISTED / AWOL / TERMINATED', 'DECEASED')
                OR ei.[reason_for_update] IS NULL
            )
            AND ei.[status] = 'Active'
     ) x
     WHERE x.[rn] = 1
     ORDER BY [last_name], [first_name]"
);
$stmt->execute([':branch' => $requested_branch]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));