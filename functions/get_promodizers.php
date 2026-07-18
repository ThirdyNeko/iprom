<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$pdo = qa_db();

$draw   = intval($_GET['draw'] ?? 1);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 25);

// =========================
// SP FILTERS
// Coerce empty strings → null so SP's IS NULL checks work correctly
// corpo is intentionally NOT passed to the SP — it only exists on
// IPROM.dbo.branches, not on whatever table @corpo would filter.
// It's filtered in PHP below using $branchMap instead.
// =========================
function nullIfEmpty($val) {
    $v = trim($val ?? '');
    return $v !== '' ? $v : null;
}

$filters = [
    ':branch'            => nullIfEmpty($_GET['branch']            ?? ''),
    ':brand'             => nullIfEmpty($_GET['brand']             ?? ''),
    ':status'            => nullIfEmpty($_GET['status']            ?? ''),
    ':assigned_by'       => nullIfEmpty($_GET['assigned_by']       ?? ''),
    ':from_date'         => nullIfEmpty($_GET['from_date']         ?? ''),
    ':to_date'           => nullIfEmpty($_GET['to_date']           ?? ''),
    ':employment_status' => nullIfEmpty($_GET['employment_status'] ?? ''),
    ':sub_status'        => nullIfEmpty($_GET['sub_status']        ?? ''),
    ':search'            => nullIfEmpty($_GET['name_search']       ?? ''),
    ':corpo'             => null, // always null — filtered in PHP via branchMap
    ':agency'            => nullIfEmpty($_GET['agency']            ?? ''),
];

// PHP-side filters (SP has no usable params for these)
$region = trim($_GET['region'] ?? '');
$area   = trim($_GET['area']   ?? '');
$corpo  = trim($_GET['corpo']  ?? '');

// Session / role
$sessionBranches = !empty($_SESSION['branch'])
    ? array_map('trim', explode(',', $_SESSION['branch']))
    : [];
$isStaff = isset($_SESSION['role']) && $_SESSION['role'] === 'staff' || isset($_SESSION['role']) && $_SESSION['role'] === 'branch_manager';

// =========================
// BRANCH MAP
// Needed for branch_display and region/area/corpo PHP filtering
// =========================
$branchMap = [];
$bmStmt = $pdo->query("
    SELECT branch_code, branch, area, region, corpo
    FROM IPROM.dbo.branches
    WHERE status = 1
");
while ($row = $bmStmt->fetch(PDO::FETCH_ASSOC)) {
    $branchMap[$row['branch_code']] = $row;
}

// =========================
// EXECUTE SP
// =========================
$stmt = $pdo->prepare("EXEC get_promodizers 
    @branch            = :branch,
    @brand             = :brand,
    @status            = :status,
    @assigned_by       = :assigned_by,
    @from_date         = :from_date,
    @to_date           = :to_date,
    @employment_status = :employment_status,
    @sub_status        = :sub_status,
    @search            = :search,
    @corpo             = :corpo,
    @agency            = :agency
");

foreach ($filters as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================
// STAFF RESTRICTION
// =========================
if ($isStaff) {
    if (empty($sessionBranches)) {
        $rows = [];
    } else {
        $rows = array_values(array_filter(
            $rows,
            fn($p) => in_array(trim($p['branch']), $sessionBranches)
        ));
    }
}

// =========================
// REGION / AREA / CORPO FILTER (PHP-side, via branchMap)
// =========================
if ($region !== '') {
    $rows = array_values(array_filter(
        $rows,
        fn($p) => ($branchMap[trim($p['branch'])]['region'] ?? '') === $region
    ));
}

if ($area !== '') {
    $rows = array_values(array_filter(
        $rows,
        fn($p) => ($branchMap[trim($p['branch'])]['area'] ?? '') === $area
    ));
}

if ($corpo !== '') {
    $rows = array_values(array_filter(
        $rows,
        fn($p) => strcasecmp(trim($branchMap[trim($p['branch'])]['corpo'] ?? ''), $corpo) === 0
    ));
}

// =========================
// COUNTS
// SP already applies all other filters, so total = filtered here
// =========================
$recordsTotal    = count($rows);
$recordsFiltered = count($rows);

// =========================
// ADD branch_display + corpo TO EACH ROW
// =========================
$rows = array_map(function ($p) use ($branchMap) {
    $branchInfo = $branchMap[trim($p['branch'])] ?? null;
    $p['branch_display'] = $branchInfo['branch'] ?? $p['branch'];
    $p['corpo']           = $branchInfo['corpo']  ?? '';
    return $p;
}, $rows);

// =========================
// PAGINATE
// =========================
$data = array_slice($rows, $start, $length);

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => array_values($data),
]);