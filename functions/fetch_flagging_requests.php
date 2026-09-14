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

$user_role   = $_SESSION['role'] ?? '';
$user_branch = $_SESSION['branch'] ?? ''; // comma-delimited

// --- DataTables server-side request params ---
$draw   = (int) ($_GET['draw'] ?? 1);
$start  = (int) ($_GET['start'] ?? 0);
$length = (int) ($_GET['length'] ?? 10);
if ($length < 1) $length = 10;

$searchValue  = $_GET['search']['value'] ?? '';
$statusFilter = $_GET['status'] ?? null; // optional, e.g. dropdown to view only Flagged/Unflagged

// Column order here MUST match the <thead> column order in flagging_requests.php
$sortColumns = ['full_name', 'branch', 'brand', 'employment_status', 'status', 'requested_by', 'requested_date'];

$orderColIndex = $_GET['order'][0]['column'] ?? 6; // default: requested_date
$orderDir      = strtoupper($_GET['order'][0]['dir'] ?? 'DESC');
$orderDir      = in_array($orderDir, ['ASC', 'DESC'], true) ? $orderDir : 'DESC';
$sortColumn    = $sortColumns[$orderColIndex] ?? 'requested_date';

try {
    $stmt = $pdo->prepare("{CALL get_flagging_requests(?, ?, ?, ?, ?, ?, ?, ?)}");
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
    echo json_encode(['error' => 'Failed to load flagging requests.']);
}