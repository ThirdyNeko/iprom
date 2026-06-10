<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../config/db.php';

$pdo = qa_db();

// -------------------------
// DATA TABLES INPUT
// -------------------------
$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');

// -------------------------
// CUSTOM FILTERS
// -------------------------
$user         = trim($_POST['user'] ?? '');
$reason       = trim($_POST['reason'] ?? '');
$remarks      = trim($_POST['remarks'] ?? '');
$remarksEmpty = (int)($_POST['remarks_empty'] ?? 0);
$from_date    = $_POST['from_date'] ?? null;
$to_date      = $_POST['to_date'] ?? null;

// -------------------------
// BASE QUERY
// -------------------------
$baseQuery = "
FROM employee_reason_history h
LEFT JOIN employee_info i 
    ON h.employee_id = i.employee_id
";

// -------------------------
// CONDITIONS
// -------------------------
$conditions = [];
$params = [];

// STAFF BRANCH FILTER
$isStaff = isset($_SESSION['role']) && $_SESSION['role'] === 'staff';

if ($isStaff) {
    $sessionBranches = array_values(array_filter(
        explode(',', $_SESSION['branch'] ?? ''),
        fn($v) => trim($v) !== ''
    ));

    if (!empty($sessionBranches)) {
        $branchPlaceholders = [];
        foreach ($sessionBranches as $idx => $branchVal) {
            $key = ":branch_{$idx}";
            $branchPlaceholders[] = $key;
            $params[$key] = $branchVal;
        }

        $inList = implode(', ', $branchPlaceholders);
        $conditions[] = "h.employee_id IN (
            SELECT employee_id FROM employee_info
            WHERE branch IN ($inList)
        )";
    } else {
        $conditions[] = "1 = 0";
    }

    $conditions[] = "h.update_date >= DATEADD(MONTH, -1, GETDATE())";
}

// GLOBAL SEARCH
if ($search !== '') {
    $conditions[] = "(
        h.reason_for_update LIKE :search
        OR h.remarks LIKE :search
        OR i.first_name LIKE :search
        OR i.last_name LIKE :search
        OR h.updated_by LIKE :search
    )";
    $params[':search'] = "%$search%";
}

// USER FILTER ✅ Fixed: was using $sql .= instead of $conditions[]
if ($user !== '') {
    if (strtolower($user) === 'system') {
        $conditions[] = "h.updated_by IS NULL";
    } else {
        $conditions[] = "h.updated_by LIKE :user";
        $params[':user'] = "%$user%";
    }
}

// REASON FILTER
if ($reason !== '') {
    $conditions[] = "h.reason_for_update LIKE :reason";
    $params[':reason'] = "%$reason%";
}

// REMARKS FILTER
if ($remarksEmpty) {
    $conditions[] = "(h.remarks IS NULL OR LTRIM(RTRIM(h.remarks)) = '')";
} elseif ($remarks !== '') {
    $conditions[] = "h.remarks LIKE :remarks";
    $params[':remarks'] = "%$remarks%";
}

// DATE FILTERS
if (!empty($from_date)) {
    $conditions[] = "CAST(h.update_date AS DATE) >= :from_date";
    $params[':from_date'] = $from_date;
}

if (!empty($to_date)) {
    $conditions[] = "CAST(h.update_date AS DATE) <= :to_date";
    $params[':to_date'] = $to_date;
}

// BUILD WHERE
$where = count($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// -------------------------
// TOTAL RECORDS
// -------------------------
$totalStmt = $pdo->query("SELECT COUNT(*) FROM employee_reason_history");
$recordsTotal = (int)$totalStmt->fetchColumn();

// -------------------------
// FILTERED COUNT
// -------------------------
$countSql = "SELECT COUNT(*) $baseQuery $where";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params); // ✅ cleaner than foreach bindValue
$recordsFiltered = (int)$countStmt->fetchColumn();

// -------------------------
// PAGINATION
// -------------------------
$startRow = $start + 1;
$endRow   = $start + $length;

// -------------------------
// MAIN QUERY
// -------------------------
$sql = "
SELECT *
FROM (
    SELECT 
        h.id,
        h.reason_for_update,
        h.update_date,
        h.remarks,
        h.employee_id,
        h.updated_by,
        i.first_name,
        i.last_name,
        ROW_NUMBER() OVER (ORDER BY h.update_date DESC) AS rn
    $baseQuery
    $where
) t
WHERE t.rn BETWEEN :startRow AND :endRow
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [
    ':startRow' => $startRow,
    ':endRow'   => $endRow,
]));

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------
// FORMAT OUTPUT
// -------------------------
$rows = [];

foreach ($data as $row) {
    $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    if ($fullName === '') $fullName = '-';

    $rows[] = [
        $row['updated_by'] ?: 'SYSTEM',
        $row['reason_for_update'] ?? '-',
        trim($row['remarks'] ?? '') !== '' ? $row['remarks'] : '-',
        $fullName,
        !empty($row['update_date'])
            ? date('m/d/y', strtotime($row['update_date']))
            : '-'
    ];
}

// -------------------------
// RESPONSE
// -------------------------
echo json_encode([
    "draw"            => $draw,
    "recordsTotal"    => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data"            => $rows
]);