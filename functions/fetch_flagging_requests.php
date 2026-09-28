<?php
/**
 * functions/fetch_flagging_requests.php
 *
 * Server-side DataTable feed for the Flagging Requests table.
 * Pagination/sorting handled in SQL via get_flagging_requests, same
 * pattern used by other paginated endpoints in IPROM.
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

$user_role = $_SESSION['role'] ?? '';

/*
 * ============================================================
 * SESSION BRANCH ENFORCEMENT
 * ============================================================
 *
 * Regional managers:
 *   $_SESSION['user_branches'] contains the branch codes/names
 *   resolved for their region.
 *
 * Other users:
 *   $_SESSION['branch'] contains comma-delimited branches.
 */

$isRegional = ($user_role === 'regional_manager');

if ($isRegional) {

    // Regional managers can only see branches assigned to their region.
    $sessionBranches = array_values(array_filter(
        array_map('trim', $_SESSION['user_branches'] ?? [])
    ));

    // Fail closed.
    if (empty($sessionBranches)) {
        echo json_encode([
            'draw'            => (int) ($_GET['draw'] ?? 1),
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => []
        ]);
        exit;
    }

    // Pass allowed branches as a comma-delimited value.
    $user_branch = implode(',', $sessionBranches);

} else {

    // Existing behavior for non-regional users.
    $user_branch = $_SESSION['branch'] ?? '';

}


// --- DataTables server-side request params ---

$draw   = (int) ($_GET['draw'] ?? 1);
$start  = (int) ($_GET['start'] ?? 0);
$length = (int) ($_GET['length'] ?? 10);

if ($length < 1) {
    $length = 10;
}

$searchValue  = $_GET['search']['value'] ?? '';
$statusFilter = $_GET['status'] ?? null;


// Column order MUST match the <thead> column order in flagging_requests.php
$sortColumns = [
    'full_name',
    'branch',
    'brand',
    'employment_status',
    'sub_status',
    'status',
    'requested_by',
    'requested_date'
];

$orderColIndex = $_GET['order'][0]['column'] ?? 7;
$orderDir      = strtoupper($_GET['order'][0]['dir'] ?? 'DESC');

$orderDir = in_array($orderDir, ['ASC', 'DESC'], true)
    ? $orderDir
    : 'DESC';

$sortColumn = $sortColumns[$orderColIndex] ?? 'requested_date';


try {

    $stmt = $pdo->prepare(
        "{CALL get_flagging_requests(?, ?, ?, ?, ?, ?, ?, ?)}"
    );

    $stmt->execute([
        nullIfEmpty($searchValue),
        nullIfEmpty($statusFilter),
        nullIfEmpty($user_role),
        nullIfEmpty($user_branch),
        $sortColumn,
        $orderDir,
        $start,
        $length,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalCount = $rows[0]['TotalCount'] ?? 0;

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => (int) $totalCount,
        'recordsFiltered' => (int) $totalCount,
        'data'            => $rows,
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'error' => 'Failed to load flagging requests.'
    ]);
}
?>