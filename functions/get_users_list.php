<?php
session_start();

include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

const AUDIT_ROLES = ['audit_manager', 'audit_supervisor', 'audit_staff'];

// 🔒 Same gate as users.php
if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['super_admin', 'admin', 'supervisor', 'audit_manager', 'audit_supervisor'])
) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$scope = $_GET['scope'] ?? 'users'; // 'users' | 'bm' | 'audit'

/* =========================
   SCOPE PERMISSION (defense-in-depth on top of the role-visibility filter below)
========================= */
$hrRoles    = ['super_admin', 'admin', 'supervisor'];
$auditRoles = ['super_admin', 'audit_manager', 'audit_supervisor'];

if (in_array($scope, ['users', 'bm']) && !in_array($_SESSION['role'], $hrRoles)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
if ($scope === 'audit' && !in_array($_SESSION['role'], $auditRoles)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$pdo = qa_db();

/* =========================
   ROLE VISIBILITY (mirrors users.php)
========================= */
$visibleRoles = match ($_SESSION['role']) {
    'super_admin'      => ['admin', 'supervisor', 'staff', 'branch_manager', 'assistant_admin', 'audit_manager', 'audit_supervisor', 'audit_staff'],
    'admin'            => ['supervisor', 'staff', 'branch_manager', 'assistant_admin'],
    'supervisor'       => ['staff', 'branch_manager'],
    'audit_manager'    => ['audit_supervisor', 'audit_staff'],
    'audit_supervisor' => ['audit_staff'],
    default            => []
};

$hiddenUsernames = ['QA_HR_ADMIN', 'QA_HR_SUPERVISOR', 'QA_HR_STAFF'];
$excludeUsernames = in_array($_SESSION['role'], ['admin', 'supervisor'])
    ? $hiddenUsernames
    : [];

$roleLabels = [
    'admin'            => 'ADMIN',
    'super_admin'      => 'SUPER ADMIN',
    'staff'            => 'STAFF',
    'supervisor'       => 'SUPERVISOR',
    'branch_manager'   => 'BRANCH MANAGER',
    'assistant_admin'  => 'ASSISTANT ADMIN',
    'audit_manager'    => 'AUDIT MANAGER',
    'audit_supervisor' => 'AUDIT SUPERVISOR',
    'audit_staff'      => 'AUDIT STAFF',
];

/* =========================
   FETCH + BASE FILTER
========================= */
$users = $pdo->query("EXEC get_users @role = NULL")->fetchAll(PDO::FETCH_ASSOC);

$users = array_values(array_filter($users, fn($u) =>
    in_array($u['role'], $visibleRoles) &&
    !in_array($u['username'], $excludeUsernames)
));

/* =========================
   BRANCH CODE -> NAME MAP (only needed for the BM tab)
========================= */
$branchNameMap = [];
if ($scope === 'bm') {
    try {
        $stmt = $pdo->prepare("EXEC dbo.get_branches_brands @branch = NULL");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $branchNameMap[$b['branch_code']] = $b['branch'];
        }
    } catch (PDOException $e) {
        $branchNameMap = [];
    }
}

function bm_branch_name(array $u, array $map): string {
    if (empty($u['branch'])) return '-';
    $code = trim(explode(',', $u['branch'])[0]);
    return $map[$code] ?? $code;
}

/* =========================
   SPLIT BY SCOPE + SHAPE ROWS
========================= */
$rows = [];

if ($scope === 'bm') {
    foreach (array_filter($users, fn($u) => $u['role'] === 'branch_manager') as $u) {
        $rows[] = [
            'id'       => $u['id'],
            'username' => $u['username'],
            'branch'   => bm_branch_name($u, $branchNameMap),
            'position' => $u['position'] ?? '-',
            'status'   => strtolower($u['status']) === 'active' ? 'active' : 'inactive',
        ];
    }
} elseif ($scope === 'audit') {
    foreach (array_filter($users, fn($u) => in_array($u['role'], AUDIT_ROLES)) as $u) {
        $rows[] = [
            'id'         => $u['id'],
            'username'   => $u['username'],
            'role'       => $u['role'],
            'role_label' => $roleLabels[$u['role']] ?? strtoupper($u['role']),
            'position'   => $u['position'] ?? '-',
            'status'     => strtolower($u['status']) === 'active' ? 'active' : 'inactive',
        ];
    }
} else { // 'users'
    foreach (array_filter($users, fn($u) => $u['role'] !== 'branch_manager' && !in_array($u['role'], AUDIT_ROLES)) as $u) {
        $rows[] = [
            'id'         => $u['id'],
            'username'   => $u['username'],
            'role'       => $u['role'],
            'role_label' => $roleLabels[$u['role']] ?? strtoupper($u['role']),
            'position'   => $u['position'] ?? '-',
            'status'     => strtolower($u['status']) === 'active' ? 'active' : 'inactive',
        ];
    }
}

$recordsTotal = count($rows);

/* =========================
   COLUMN INDEX -> FIELD MAP (per tab)
========================= */
$columnMap = match ($scope) {
    'bm'    => [0 => 'username', 1 => 'branch', 2 => 'position', 3 => 'status'],
    default => [0 => 'username', 1 => 'role_label', 2 => 'position', 3 => 'status'], // 'users' and 'audit'
};

/* =========================
   PER-COLUMN SEARCH (from the custom filter inputs)
========================= */
$columnsParam = $_GET['columns'] ?? [];

foreach ($columnsParam as $idx => $col) {
    $idx = (int) $idx;
    if (!isset($columnMap[$idx])) continue;

    $value = $col['search']['value'] ?? '';
    if ($value === '') continue;

    $field = $columnMap[$idx];
    $isRegex = filter_var($col['search']['regex'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // The status filter sends an exact "^active$" / "^inactive$" style regex.
    // Everything else (username/position/branch) is a plain substring search.
    if ($isRegex && preg_match('/^\^(.*)\$$/', $value, $m)) {
        $needle = strtolower($m[1]);
        $rows = array_values(array_filter($rows, fn($r) => strtolower($r[$field] ?? '') === $needle));
    } else {
        $needle = strtolower($value);
        $rows = array_values(array_filter($rows, fn($r) => str_contains(strtolower($r[$field] ?? ''), $needle)));
    }
}

$recordsFiltered = count($rows);

/* =========================
   SORT
========================= */
$order = $_GET['order'] ?? [];
if (!empty($order)) {
    $col = (int) ($order[0]['column'] ?? 0);
    $dir = ($order[0]['dir'] ?? 'asc') === 'desc' ? -1 : 1;

    if (isset($columnMap[$col])) {
        $field = $columnMap[$col];
        usort($rows, function ($a, $b) use ($field, $dir) {
            return strcasecmp($a[$field] ?? '', $b[$field] ?? '') * $dir;
        });
    }
}

/* =========================
   PAGE
========================= */
$start  = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 25);

$paged = $length === -1 ? array_slice($rows, $start) : array_slice($rows, $start, $length);

echo json_encode([
    'draw'            => (int) ($_GET['draw'] ?? 1),
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => $paged,
]);