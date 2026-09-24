<?php
session_start();
require_once '../config/db.php';
require_once '../auth/require_login.php';

// nullIfEmpty() is used across the app's other functions/*.php files but
// isn't pulled in by the includes above in this codebase's layout — guard
// it here so this file doesn't depend on knowing which helper file it
// actually lives in. If you have a shared helpers.php, require that
// instead and delete this block.
if (!function_exists('nullIfEmpty')) {
    function nullIfEmpty($value)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}

header('Content-Type: application/json');

$pdo = qa_db();

$branch = nullIfEmpty($_GET['branch'] ?? 'ALL') ?? 'ALL';
$brand  = nullIfEmpty($_GET['brand']  ?? 'ALL') ?? 'ALL';
$period = nullIfEmpty($_GET['period'] ?? 'all') ?? 'all';

/*
 * ─── Logic ─────────────────────────────────────────────────────────────────
 * 1. ActiveFlags: count flagging_request rows per employee_id that are NOT
 *    unflagged (unflagged_date IS NULL), plus the oldest such request date.
 *    This drives which employees qualify for the report and the "days
 *    since oldest flag" figure.
 * 2. AllFlagCounts: total flagging_request rows per employee_id regardless
 *    of unflagged status — this is the Flag Count column (flagged +
 *    unflagged both count).
 * 3. EmployeeMatch: employee_info rows (filtered by branch/brand) whose
 *    employee_id has at least one active flag.
 * 4. BranchList: per matched employee, DISTINCT branch/brand values across
 *    ALL of their employee_info rows, comma-joined via the FOR XML PATH
 *    trick (STRING_AGG isn't available on SQL Server 2012).
 * 5. Final SELECT joins it all together and applies the "days since oldest
 *    flag" bucket filter (period).
 *
 * NOTE: named placeholders are repeated in this query (e.g. :branch is used
 * twice), which some ODBC drivers reject unless PDO::ATTR_EMULATE_PREPARES
 * is on. To stay driver-agnostic, each occurrence gets its own placeholder
 * name (:branch1/:branch2, :period1..:period5) bound to the same value.
 */
$sql = "
;WITH ActiveFlags AS (
    SELECT
        fr.[employee_id],
        MIN(fr.[requested_date])    AS oldest_flag_date
    FROM [flagging_request] fr
    WHERE fr.[unflagged_date] IS NULL
    GROUP BY fr.[employee_id]
),
AllFlagCounts AS (
    SELECT
        fr.[employee_id],
        COUNT(*) AS flag_count
    FROM [flagging_request] fr
    GROUP BY fr.[employee_id]
),
EmployeeMatch AS (
    SELECT DISTINCT ei.[employee_id]
    FROM [employee_info] ei
    INNER JOIN ActiveFlags af ON af.[employee_id] = ei.[employee_id]
    WHERE (:branch1 = 'ALL' OR ei.[branch] COLLATE DATABASE_DEFAULT = :branch2 COLLATE DATABASE_DEFAULT)
      AND (:brand1  = 'ALL' OR ei.[brand]  COLLATE DATABASE_DEFAULT = :brand2  COLLATE DATABASE_DEFAULT)
),
BranchList AS (
    SELECT
        em.[employee_id],
        -- Resolve branch_code -> display name the same way reports.php /
        -- get_vacant_plantilla.php do: LEFT JOIN branches on branch_code,
        -- with COLLATE DATABASE_DEFAULT to dodge cross-table collation
        -- mismatches. Falls back to the raw code if no branches row matches.
        STUFF((
            SELECT DISTINCT ',' + COALESCE(b2.[branch], ei2.[branch])
            FROM [employee_info] ei2
            LEFT JOIN [branches] b2
                ON ei2.[branch] COLLATE DATABASE_DEFAULT = b2.[branch_code] COLLATE DATABASE_DEFAULT
            WHERE ei2.[employee_id] = em.[employee_id]
              AND ei2.[branch] IS NOT NULL
            FOR XML PATH(''), TYPE
        ).value('.', 'NVARCHAR(MAX)'), 1, 1, '') AS branches,
        STUFF((
            SELECT DISTINCT ',' + ei3.[brand]
            FROM [employee_info] ei3
            WHERE ei3.[employee_id] = em.[employee_id]
              AND ei3.[brand] IS NOT NULL
            FOR XML PATH(''), TYPE
        ).value('.', 'NVARCHAR(MAX)'), 1, 1, '') AS brands
    FROM EmployeeMatch em
)
SELECT
    bl.[employee_id],
    bl.[branches],
    bl.[brands],
    nm.[last_name],
    nm.[first_name],
    nm.[middle_name],
    afc.[flag_count],
    af.[oldest_flag_date],
    DATEDIFF(DAY, af.[oldest_flag_date], GETDATE()) AS days_since_oldest
FROM BranchList bl
INNER JOIN ActiveFlags af ON af.[employee_id] = bl.[employee_id]
INNER JOIN AllFlagCounts afc ON afc.[employee_id] = bl.[employee_id]
OUTER APPLY (
    SELECT TOP 1 ei4.[last_name], ei4.[first_name], ei4.[middle_name]
    FROM [employee_info] ei4
    WHERE ei4.[employee_id] = bl.[employee_id]
    ORDER BY ei4.[updated_at] DESC
) nm
WHERE (
       :period1 = 'all'
    OR (:period2 = 'lt15'     AND DATEDIFF(DAY, af.[oldest_flag_date], GETDATE()) < 15)
    OR (:period3 = '15_30'    AND DATEDIFF(DAY, af.[oldest_flag_date], GETDATE()) BETWEEN 15 AND 30)
    OR (:period4 = '1_2mo'    AND DATEDIFF(DAY, af.[oldest_flag_date], GETDATE()) BETWEEN 31 AND 60)
    OR (:period5 = '2mo_plus' AND DATEDIFF(DAY, af.[oldest_flag_date], GETDATE()) > 60)
)
ORDER BY af.[oldest_flag_date] ASC;
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':branch1' => $branch, ':branch2' => $branch,
    ':brand1'  => $brand,  ':brand2'  => $brand,
    ':period1' => $period, ':period2' => $period, ':period3' => $period,
    ':period4' => $period, ':period5' => $period,
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = array_map(function ($r) {
    return [
        'employee_id'       => $r['employee_id'],
        'branches'          => $r['branches'],
        'brands'            => $r['brands'],
        'name'              => formatEmployeeName($r['last_name'], $r['first_name'], $r['middle_name']),
        'flag_count'        => (int) $r['flag_count'],
        'oldest_flag_date'  => $r['oldest_flag_date'],
        'days_since_oldest' => (int) $r['days_since_oldest'],
        'days_since_label'  => formatDaysSinceLabel((int) $r['days_since_oldest']),
    ];
}, $rows);

echo json_encode($data);

/**
 * "Last Name, First Name MI."
 */
function formatEmployeeName($last, $first, $middle)
{
    $mi = $middle ? (' ' . strtoupper(substr(trim($middle), 0, 1)) . '.') : '';
    return trim((string) $last) . ', ' . trim((string) $first) . $mi;
}

/**
 * "X days" while under a month, then "Y month(s), Z day(s)" once it rolls
 * over — matches the "counted in days then month" display requirement.
 */
function formatDaysSinceLabel($days)
{
    if ($days < 30) {
        return $days . ' day' . ($days === 1 ? '' : 's');
    }
    $months  = intdiv($days, 30);
    $remDays = $days % 30;
    $label   = $months . ' month' . ($months === 1 ? '' : 's');
    if ($remDays > 0) {
        $label .= ', ' . $remDays . ' day' . ($remDays === 1 ? '' : 's');
    }
    return $label;
}