<?php
/**
 * ajax/fetch_all_branches.php
 *
 * Returns every branch (code + display name) — used to populate the
 * branch dropdown in the Request Blacklist modal. Also used for
 * branch_manager to resolve the display name of their one locked branch
 * (they only get their own code back out of this list, filtered client-side).
 *
 * Dropdown displays [branch] (name) but the value submitted/stored is
 * [branch_code], matching how employee_info.branch / blacklist_request.branch
 * store codes (same convention as get_blacklisted's join).
 */

session_start();
header('Content-Type: application/json');

require '../config/db.php';
require '../auth/require_login.php';

$user_role = strtolower($_SESSION['role'] ?? '');

$allowed_roles = ['audit_manager', 'audit_supervisor', 'branch_manager', 'admin', 'super_admin'];

if (!in_array($user_role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized.']);
    exit;
}

$pdo = qa_db();

$stmt = $pdo->query("SELECT [branch_code], [branch] FROM dbo.[branches] ORDER BY [branch]");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($branches);