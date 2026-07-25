<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

$draw   = $_POST['draw'] ?? 0;
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 25);
$name   = trim($_POST['name'] ?? '');

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
    ? "LTRIM(RTRIM(first_name + ' ' + ISNULL(middle_name, '') + ' ' + last_name + ' ' + ISNULL(suffix, '')))"
    : $orderColumn;

$params       = [];   // full param set (branch + name search), used for the main/filtered query
$branchParams = [];   // branch-only params, used for the unfiltered recordsTotal count

// ── Branch restriction ──────────────────────────────────────────
// branch manager / staff only see LOAs tied to their own branch(es).
// $_SESSION['branch'] is set at login (auth/login.php) as a
// comma-delimited string of branch codes, same pattern used
// elsewhere in the app (dashboard/promodizers/assignments filtering).
// Enforced here from the session (server-side) — never trust a
// branch value posted from the client.
$sessionRole     = strtolower(trim($_SESSION['role'] ?? ''));
$sessionBranch   = $_SESSION['branch'] ?? '';
$restrictedRoles = ['branch_manager', 'staff'];

$branchWhere = "WHERE 1=1";
$branchCodes = []; // kept in scope outside the if-block below so it can be
                    // reused later to scope roving_branches per-row

if (in_array($sessionRole, $restrictedRoles, true)) {
    $branchCodes = array_values(array_filter(array_map('trim', explode(',', $sessionBranch))));

    if (empty($branchCodes)) {
        // Restricted role with no branch assigned -> see nothing, fail closed.
        $branchWhere .= " AND 1 = 0";
    } else {
        $branchConditions = [];
        foreach ($branchCodes as $i => $code) {
            // Match on the LOA record's own home branch ONLY.
            //
            // letters_of_advice stores one row PER branch for a multi-branch
            // employee (e.g. id 13: branch_code=TIGB, roving_branches=VIAC;
            // id 14: branch_code=VIAC, roving_branches=TIGB, same employee).
            // Matching on roving_branches too used to pull in row 14 for a
            // TIGB-only manager just because "TIGB" appeared in its roving
            // list -- effectively showing every branch's LOA record as soon
            // as the manager's branch touched it anywhere. A manager should
            // only see the record whose own branch_code is theirs.
            $key = ":branch{$i}";
            $branchConditions[] = "branch_code = {$key}";
            $branchParams[$key] = $code;
        }
        $branchWhere .= " AND (" . implode(' OR ', $branchConditions) . ")";
    }
}

$params = $branchParams;

// ── Name/search filter (applied on top of the branch restriction) ──
$where = $branchWhere;

if (!empty($name)) {
    $where .= " AND (
        first_name        LIKE :name1 OR
        last_name         LIKE :name2 OR
        middle_name       LIKE :name3 OR
        agency            LIKE :name4 OR
        employment_status LIKE :name5 OR
        sub_status        LIKE :name6
    )";
    $params[':name1'] = "%$name%";
    $params[':name2'] = "%$name%";
    $params[':name3'] = "%$name%";
    $params[':name4'] = "%$name%";
    $params[':name5'] = "%$name%";
    $params[':name6'] = "%$name%";
}

// recordsTotal reflects what this user is allowed to see (branch-restricted,
// no search filter) — not the whole table — so DataTables' "X of Y entries"
// footer isn't misleading for restricted roles.
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM letters_of_advice $branchWhere");
$totalStmt->execute($branchParams);
$recordsTotal = $totalStmt->fetchColumn();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM letters_of_advice $where");
$countStmt->execute($params);
$recordsFiltered = $countStmt->fetchColumn();

$sql = "
SELECT *
FROM (
    SELECT
        id        AS loa_id,
        employee_id,
        -- Display column
        LTRIM(RTRIM(
            first_name + ' ' +
            ISNULL(middle_name, '') + ' ' +
            last_name + ' ' +
            ISNULL(suffix, '')
        )) AS promodiser,
        -- Individual name parts for PDF
        first_name,
        middle_name,
        last_name,
        ISNULL(suffix, '')      AS suffix,
        -- Recipient
        recipient_name,
        recipient_position,
        -- Branch / brand / agency
        branch_code,
        ISNULL(roving_branches, '') AS roving_branches,
        brand,
        ISNULL(multi_brands, '') AS multi_brands,
        agency,
        -- Status fields
        employment_status,
        sub_status,
        status,
        -- Dates
        effectivity_date,
        end_date,
        -- Remarks
        ISNULL(remarks, '') AS remarks,
        -- Original issuer, used when reprinting (loa_table.js) so the PDF
        -- shows who ACTUALLY issued it, not the current viewer.
        ISNULL(issued_by, '')       AS issued_by,
        ISNULL(issued_position, '') AS issued_position,
        ROW_NUMBER() OVER (ORDER BY $orderExpr $orderDir) AS rownum
    FROM letters_of_advice
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

    // Format effectivity_date for display only; keep raw date for the PDF payload
    if (!empty($row['effectivity_date'])) {
        $row['effectivity_date_display'] = date('M d, Y', strtotime($row['effectivity_date']));
        // leave $row['effectivity_date'] as raw Y-m-d for generate_letter_pdf.php
    }

    // Explode comma-delimited strings back into arrays for JSON
    $row['roving_branches'] = !empty($row['roving_branches'])
        ? explode(',', $row['roving_branches'])
        : [];

    // Branch managers / staff only get to see the roving branch(es) that
    // are actually theirs — a roving employee assigned to BR01,BR02,BR05
    // should not reveal BR02/BR05 to a manager who only manages BR01.
    // Admin/super_admin (not in $restrictedRoles) still see the full list.
    if (in_array($sessionRole, $restrictedRoles, true) && !empty($branchCodes)) {
        $row['roving_branches'] = array_values(
            array_intersect($row['roving_branches'], $branchCodes)
        );
    }

    $row['multi_brands'] = !empty($row['multi_brands'])
        ? explode(',', $row['multi_brands'])
        : [];
}

echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => intval($recordsTotal),
    "recordsFiltered" => intval($recordsFiltered),
    "data"            => $data,
]);