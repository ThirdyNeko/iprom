<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

$draw   = $_POST['draw'] ?? 0;
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 25);
$name   = trim($_POST['name'] ?? '');

// Branch dropdown selection from the page. This can only NARROW results:
// it is ANDed on top of the role/branch/region restriction below, so a
// tampered value can never widen what the caller is allowed to see.
$branchFilter = trim($_POST['branch_filter'] ?? '');

$columns = [
    0 => 'promodiser',
    1 => 'agency',
    2 => 'employment_status',
    3 => 'sub_status',
    4 => 'effectivity_date',
];

$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$orderColumn = $columns[$orderColumnIndex] ?? 'promodiser';

$orderExpr = ($orderColumn === 'promodiser')
    ? "LTRIM(RTRIM(loa.first_name + ' ' + ISNULL(loa.middle_name, '') + ' ' + loa.last_name + ' ' + ISNULL(loa.suffix, '')))"
    : $orderColumn;

$params       = [];   // full param set (branch + name search), used for the main/filtered query
$branchParams = [];   // branch-only params, used for the unfiltered recordsTotal count

// ── Branch restriction ──────────────────────────────────────────
// branch_manager / staff: limited to the branch codes in $_SESSION['branch']
//   (comma-delimited, set at login).
// regional_manager: limited to every branch in branches.region matching
//   $_SESSION['region'] (no `deployed` check, mirroring branch_manager access).
// Enforced from the session (server-side) — never trust a value posted
// from the client.
$sessionRole     = strtolower(trim($_SESSION['role'] ?? ''));
$sessionBranch   = $_SESSION['branch'] ?? '';
$sessionRegion   = trim($_SESSION['region'] ?? '');
$restrictedRoles = ['branch_manager', 'staff', 'regional_manager'];

// LOA code is sensitive (used to verify a promodiser's identity at the
// branch during the Verify flow) — only admin/super_admin should ever
// receive it in the JSON payload. Masked server-side further down.
$canViewLoaCode = in_array($sessionRole, ['admin', 'super_admin'], true);

$branchWhere = "WHERE 1=1";
$branchCodes = []; // reused later to scope roving_branches per-row

if (in_array($sessionRole, $restrictedRoles, true)) {

    if ($sessionRole === 'regional_manager') {
        // Resolve the region into its branch codes once, so the same
        // IN-style matching + roving_branches scoping below works for
        // both branch-based and region-based roles.
        if ($sessionRegion !== '') {
            $regStmt = $pdo->prepare("SELECT DISTINCT branch_code FROM branches WHERE region = :region");
            $regStmt->execute([':region' => $sessionRegion]);
            $branchCodes = array_values(array_filter(array_map('trim', $regStmt->fetchAll(PDO::FETCH_COLUMN))));
        }
    } else {
        $branchCodes = array_values(array_filter(array_map('trim', explode(',', $sessionBranch))));
    }

    if (empty($branchCodes)) {
        // Restricted role with no branch/region assigned -> see nothing, fail closed.
        $branchWhere .= " AND 1 = 0";
    } else {
        $branchConditions = [];
        foreach ($branchCodes as $i => $code) {
            // Match on the LOA record's own home branch ONLY (not
            // roving_branches) — see earlier note: letters_of_advice stores
            // one row PER branch for a multi-branch employee.
            $key = ":branch{$i}";
            $branchConditions[] = "loa.branch_code = {$key}";
            $branchParams[$key] = $code;
        }
        $branchWhere .= " AND (" . implode(' OR ', $branchConditions) . ")";
    }
}

$params = $branchParams;

// ── Filters applied on top of the branch restriction ──
$where = $branchWhere;

// Branch dropdown filter
if ($branchFilter !== '') {
    $where .= " AND loa.branch_code = :branchFilter";
    $params[':branchFilter'] = $branchFilter;
}

if (!empty($name)) {
    $where .= " AND (
        loa.first_name        LIKE :name1 OR
        loa.last_name         LIKE :name2 OR
        loa.middle_name       LIKE :name3 OR
        loa.agency             LIKE :name4 OR
        loa.employment_status  LIKE :name5 OR
        loa.sub_status         LIKE :name6
    )";
    $params[':name1'] = "%$name%";
    $params[':name2'] = "%$name%";
    $params[':name3'] = "%$name%";
    $params[':name4'] = "%$name%";
    $params[':name5'] = "%$name%";
    $params[':name6'] = "%$name%";
}

// recordsTotal reflects what this user is allowed to see (role-restricted,
// no dropdown/search filter).
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM letters_of_advice AS loa $branchWhere");
$totalStmt->execute($branchParams);
$recordsTotal = $totalStmt->fetchColumn();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM letters_of_advice AS loa $where");
$countStmt->execute($params);
$recordsFiltered = $countStmt->fetchColumn();

$sql = "
SELECT *
FROM (
    SELECT
        loa.id        AS loa_id,
        loa.employee_id,
        -- Display column
        LTRIM(RTRIM(
            loa.first_name + ' ' +
            ISNULL(loa.middle_name, '') + ' ' +
            loa.last_name + ' ' +
            ISNULL(loa.suffix, '')
        )) AS promodiser,
        -- Individual name parts for PDF
        loa.first_name,
        loa.middle_name,
        loa.last_name,
        ISNULL(loa.suffix, '')      AS suffix,
        -- Recipient
        loa.recipient_name,
        loa.recipient_position,
        -- Branch / brand / agency
        loa.branch_code,
        b.branch AS branch_name,
        ISNULL(loa.roving_branches, '') AS roving_branches,
        loa.brand,
        ISNULL(loa.multi_brands, '') AS multi_brands,
        loa.agency,
        -- Biometric number -- required before this LOA can be verified
        emp.biometric_number,
        -- LOA code -- sensitive; masked in the PHP output loop below
        loa.loa_code,
        -- Status fields
        loa.employment_status,
        loa.sub_status,
        loa.status,
        -- Dates
        loa.effectivity_date,
        loa.end_date,
        -- Remarks
        ISNULL(loa.remarks, '') AS remarks,
        -- Original issuer, used when reprinting
        ISNULL(loa.issued_by, '')       AS issued_by,
        ISNULL(loa.issued_position, '') AS issued_position,
        -- Last updated timestamp shown on the printed PDF
        ISNULL(loa.updated_at, GETDATE()) AS last_updated,
        ROW_NUMBER() OVER (ORDER BY $orderExpr $orderDir) AS rownum
    FROM letters_of_advice AS loa
    LEFT JOIN (
        -- De-duped: exactly one row per branch_code
        SELECT branch_code, branch,
               ROW_NUMBER() OVER (PARTITION BY branch_code ORDER BY branch_code) AS rn
        FROM branches
    ) AS b ON b.branch_code = loa.branch_code AND b.rn = 1
    LEFT JOIN (
        -- Same de-dupe for employee_info
        SELECT employee_id, biometric_number,
               ROW_NUMBER() OVER (PARTITION BY employee_id ORDER BY employee_id) AS rn
        FROM employee_info
    ) AS emp ON emp.employee_id = loa.employee_id AND emp.rn = 1
    $where
) AS t
WHERE t.rownum > :start
  AND t.rownum <= :end
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':end',   $start + $length, PDO::PARAM_INT);

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as &$row) {
    unset($row['rownum']);

    if (!$canViewLoaCode) {
        unset($row['loa_code']);
    }

    // Fallback to the raw code if the branch row wasn't found (LEFT JOIN miss).
    if (empty($row['branch_name'])) {
        $row['branch_name'] = $row['branch_code'];
    }

    // Format effectivity_date for display only; keep raw date for the PDF payload
    if (!empty($row['effectivity_date'])) {
        $row['effectivity_date_display'] = date('M d, Y', strtotime($row['effectivity_date']));
    }

    if (!empty($row['last_updated'])) {
        $row['last_updated'] = date('Y-m-d H:i:s', strtotime($row['last_updated']));
    }

    // Explode comma-delimited strings back into arrays for JSON
    $row['roving_branches'] = !empty($row['roving_branches'])
        ? explode(',', $row['roving_branches'])
        : [];

    // Restricted roles (branch_manager / staff / regional_manager) only see
    // the roving branch(es) that fall inside their own scope. For a
    // regional_manager $branchCodes is every branch in their region.
    if (in_array($sessionRole, $restrictedRoles, true) && !empty($branchCodes)) {
        $row['roving_branches'] = array_values(
            array_intersect($row['roving_branches'], $branchCodes)
        );
    }

    $row['multi_brands'] = !empty($row['multi_brands'])
        ? explode(',', $row['multi_brands'])
        : [];
}
unset($row);

echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => intval($recordsTotal),
    "recordsFiltered" => intval($recordsFiltered),
    "data"            => $data,
]);