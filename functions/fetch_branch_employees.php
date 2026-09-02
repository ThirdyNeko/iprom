<?php
/**
 * ajax/fetch_branch_employees.php
 *
 * Returns employees for a given branch, to populate the "Promodiser"
 * dropdown in the Request Blacklist modal after a branch is chosen.
 *
 * Server-side branch enforcement:
 * - branch_manager: locked to their own session branch, ignoring/rejecting
 *   any other branch value the client sends. Never trust the dropdown alone.
 * - audit_manager / audit_supervisor: may only request the branches listed
 *   in their own session branch CSV.
 * - admin / super_admin: unrestricted (not expected to use this modal, but
 *   scoping stays open in case that changes).
 */

session_start();
header('Content-Type: application/json');

require '../config/db.php';
require '../auth/require_login.php';

if (!function_exists('nullIfEmpty')) {
    function nullIfEmpty($value) {
        return ($value === null || $value === '') ? null : $value;
    }
}

$pdo = qa_db();

$user_role   = strtolower($_SESSION['role'] ?? '');
$user_branch = $_SESSION['branch'] ?? ''; // comma-delimited
$allowed_branches = array_filter(array_map('trim', explode(',', $user_branch)));

$requested_branch = trim($_GET['branch'] ?? '');

if ($requested_branch === '') {
    echo json_encode([]);
    exit;
}

if (!in_array($user_role, ['admin', 'super_admin', 'audit_manager', 'audit_supervisor'], true)) {
    // Only branch_manager (or any other restricted role) is locked to
    // their own session branch(es).
    if (!in_array($requested_branch, $allowed_branches, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not authorized to view employees for that branch.']);
        exit;
    }
}

$stmt = $pdo->prepare(
    "SELECT
        ei.[id],
        ei.[employee_id],
        ei.[first_name],
        ei.[middle_name],
        ei.[last_name],
        ei.[birthday],
        ei.[suffix],
        ei.[gender],
        ei.[marital_status],
        ei.[branch] AS [branch_code],
        COALESCE(b.[branch], ei.[branch]) AS [branch],
        ei.[brand],
        ei.[employment_status]
     FROM dbo.[employee_info] ei
     LEFT JOIN dbo.[branches] b ON b.[branch_code] = ei.[branch]
     WHERE ei.[branch] = :branch
        AND (
            ei.[reason_for_update] <> 'Clerical Error'
            OR ei.[reason_for_update] IS NULL
        )
     ORDER BY ei.[last_name], ei.[first_name]"
);
$stmt->execute([':branch' => $requested_branch]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));