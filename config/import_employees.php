<?php
/**
 * import_employees.php
 * One-time CSV import into IPROM [dbo].[employee_info]
 * Place this file in your IPROM root (same level as db.php), then open in browser.
 * DELETE or MOVE this file after import is done.
 */

include_once 'db.php';

$csvFile = __DIR__ . '/Sample_Promo_data_Sheet1_.csv';
if (!file_exists($csvFile)) die("CSV file not found: $csvFile");

// ── Helpers ───────────────────────────────────────────────────────────────────

function toSqlDate(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') return null;
    $ts = strtotime($raw);
    if ($ts === false) return null;
    return date('Y-m-d', $ts);
}

function clean(string $val): string {
    return trim(preg_replace('/\s+/', ' ', $val));
}

function toUtf8(array $row): array {
    return array_map(function($v) {
        // If already valid UTF-8 (e.g. Excel saved as UTF-8 CSV), don't convert —
        // converting again would double-encode Ñ into Ã'
        if (mb_check_encoding($v, 'UTF-8')) {
            return $v;
        }
        return mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
    }, $row);
}

function generateId(string $prefix): string {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
}

function resolveGroupId(string $lastName, string $firstName, string $prefix, array &$map): string {
    $key = mb_strtoupper(trim($lastName), 'UTF-8') . '|' . mb_strtoupper(trim($firstName), 'UTF-8');
    if (!isset($map[$key])) {
        $map[$key] = generateId($prefix);
    }
    return $map[$key];
}

function claimAssignmentSlot(string $branchCode, string $brand, array &$assignmentMap, array &$consumed): array {
    $key = strtoupper($branchCode) . '|' . mb_strtoupper($brand, 'UTF-8');
    if (!isset($assignmentMap[$key])) {
        return [false, "No assignment setup found for {$branchCode} - {$brand}."];
    }
    $available       = $assignmentMap[$key]['available'];
    $alreadyConsumed = $consumed[$key] ?? 0;
    if (($available - $alreadyConsumed) <= 0) {
        return [false, "Slot is full for {$branchCode} - {$brand} (required: {$assignmentMap[$key]['required']}, assigned: {$assignmentMap[$key]['assigned']}, imported this session: {$alreadyConsumed})."];
    }
    $consumed[$key] = $alreadyConsumed + 1;
    return [true, null];
}

// ── Connect + load lookups ────────────────────────────────────────────────────

$pdo = qa_db();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

// Branch lookup: UPPER(branch_name) → branch_code
$branchMap = [];
try {
    $rows = $pdo->query("SELECT [branch], [branch_code] FROM [IPROM].[dbo].[branches]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $branchMap[strtoupper(trim($r['branch']))] = $r['branch_code'];
    }
} catch (PDOException $e) { die("Branch lookup failed: " . $e->getMessage()); }

// Reverse branch lookup: branch_code → branch_name (for history formatting)
$branchNameMap = [];
foreach ($rows as $r) {
    $branchNameMap[strtoupper(trim($r['branch_code']))] = $r['branch'];
}

// Agency lookup: UPPER(agencies) → true
$agencySet = [];
try {
    $rows = $pdo->query("SELECT [agencies] FROM [IPROM].[dbo].[agencies]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $agencySet[mb_strtoupper(trim($r['agencies']), 'UTF-8')] = true;
    }
} catch (PDOException $e) { die("Agency lookup failed: " . $e->getMessage()); }

// Assignment lookup: UPPER(branch_code)|UPPER(brand) → slot info
$assignmentMap = [];
try {
    $rows = $pdo->query("SELECT [branch_name],[brand_name],[required_count],[assigned_count] FROM [IPROM].[dbo].[assignment]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $key = strtoupper(trim($r['branch_name'])) . '|' . mb_strtoupper(trim($r['brand_name']), 'UTF-8');
        $assignmentMap[$key] = [
            'required'  => (int)$r['required_count'],
            'assigned'  => (int)$r['assigned_count'],
            'available' => (int)$r['required_count'] - (int)$r['assigned_count'],
        ];
    }
} catch (PDOException $e) { die("Assignment lookup failed: " . $e->getMessage()); }

// Existing records: for duplicate detection
$existingSet = [];
try {
    $rows = $pdo->query("SELECT [last_name],[first_name],[birthday],[branch],[brand] FROM [IPROM].[dbo].[employee_info]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $key = mb_strtoupper(trim($r['last_name']),  'UTF-8') . '|' .
               mb_strtoupper(trim($r['first_name']), 'UTF-8') . '|' .
               substr(trim($r['birthday'] ?? ''), 0, 10)      . '|' .
               strtoupper(trim($r['branch'] ?? ''))            . '|' .
               mb_strtoupper(trim($r['brand'] ?? ''), 'UTF-8');
        $existingSet[$key] = true;
    }
} catch (PDOException $e) { die("Existing records lookup failed: " . $e->getMessage()); }

// ── PASS 1: Scan CSV — collect needs, parse rows ──────────────────────────────

$handle = fopen($csvFile, 'r');
if (!$handle) die("Cannot open CSV.");
fgetcsv($handle); // skip header

$parsedRows  = [];
$agencyNeeds = [];
$slotNeeds   = [];

while (($row = fgetcsv($handle)) !== false) {
    $row = toUtf8($row);
    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;
    while (count($row) < 15) $row[] = '';

    [
        $branch, $lastName, $firstName, $mi, $suffix,
        $gender, $birthday, $dateHired, $branchDeployed,
        $brand, $employmentStatus, $subStatus, $agency, $from, $to
    ] = $row;

    $branchKey  = strtoupper(clean($branch));
    $branchCode = $branchMap[$branchKey] ?? null;
    $brandNorm  = mb_strtoupper(clean($brand), 'UTF-8');
    $agencyNorm = mb_strtoupper(clean($agency), 'UTF-8');

    if ($agencyNorm !== '') {
        $agencyNeeds[$agencyNorm] = ($agencyNeeds[$agencyNorm] ?? 0) + 1;
    }

    if ($branchCode !== null && $brandNorm !== '' && isset($agencySet[$agencyNorm])) {
        $dupKey = mb_strtoupper(clean($lastName),  'UTF-8') . '|' .
                  mb_strtoupper(clean($firstName), 'UTF-8') . '|' .
                  (toSqlDate($birthday) ?? '')               . '|' .
                  strtoupper($branchCode)                    . '|' .
                  $brandNorm;

        if (!isset($existingSet[$dupKey])) {
            $slotKey = strtoupper($branchCode) . '|' . $brandNorm;
            if (!isset($slotNeeds[$slotKey])) {
                $slotNeeds[$slotKey] = ['branch' => $branchCode, 'brand' => $brandNorm, 'needed' => 0];
            }
            $slotNeeds[$slotKey]['needed']++;
        }
    }

    $parsedRows[] = $row;
}
fclose($handle);

// ── Pre-flight analysis ───────────────────────────────────────────────────────

$agenciesOk      = [];
$agenciesMissing = [];
foreach ($agencyNeeds as $name => $count) {
    if (isset($agencySet[$name])) $agenciesOk[$name] = $count;
    else                          $agenciesMissing[$name] = $count;
}

$slotsOk      = [];
$slotsShort   = [];
$slotsMissing = [];
foreach ($slotNeeds as $key => $info) {
    if (!isset($assignmentMap[$key])) {
        $slotsMissing[$key] = $info;
    } else {
        $available = $assignmentMap[$key]['available'];
        $info['available'] = $available;
        $info['required']  = $assignmentMap[$key]['required'];
        $info['assigned']  = $assignmentMap[$key]['assigned'];
        if ($available >= $info['needed']) $slotsOk[$key]    = $info;
        else                               $slotsShort[$key] = $info;
    }
}

$preflightPassed = empty($agenciesMissing) && empty($slotsMissing) && empty($slotsShort);

// ── PASS 2: Import ────────────────────────────────────────────────────────────

$errors               = [];
$slotErrors           = [];
$duplicates           = [];
$rejectedExport       = []; // structured data for Excel export of all rejected rows
$count                = 0;
$skipped              = 0;
$consumed             = [];
$employeeIdMap        = [];
$rovingGroupIdMap     = [];
$multiBrandGroupIdMap = [];
$historyInserted      = [];

$sql = "
    INSERT INTO [IPROM].[dbo].[employee_info] (
        [first_name],[last_name],[middle_name],[suffix],
        [gender],[birthday],[branch],[brand],
        [employment_status],[sub_status],[agency],
        [date_hired],[start_date],[end_date],[remarks],[first_remark],
        [employee_id],[roving_group_id],[multi_brand_group_id],
        [status],[hidden],[created_at],[updated_at],
        [assignment_date],[last_assigned_by],[last_updated_by]
    ) VALUES (
        :first_name,:last_name,:middle_name,:suffix,
        :gender,:birthday,:branch,:brand,
        :employment_status,:sub_status,:agency,
        :date_hired,:start_date,:end_date,:remarks,:first_remark,
        :employee_id,:roving_group_id,:multi_brand_group_id,
        'ACTIVE',0,GETDATE(),GETDATE(),
        GETDATE(),'SYSTEM','SYSTEM'
    )
";
$stmt = $pdo->prepare($sql);

$historySql = "
    INSERT INTO [IPROM].[dbo].[employee_reason_history] (
        [employee_id],[reason_for_update],[update_date],[remarks],[updated_by]
    ) VALUES (
        :employee_id,:reason_for_update,GETDATE(),:remarks,'SYSTEM'
    )
";
$historyStmt = $pdo->prepare($historySql);
$historyData = []; // employee_id → {date_hired, sub_status, branches[], brands[]} for deferred write

// Helper: capture a rejected row into $rejectedExport
$reject = function(
    array  $row,
    string $reason,
    string $branchCode = '',
    string $brandNorm  = '',
    string $agencyNorm = '',
    string $subStatusNorm = '',
    string $employmentStatus = ''
) use (&$rejectedExport, &$slotErrors) {

    [
        $branch, $lastName, $firstName, $mi, $suffix,
        $gender, $birthday, $dateHired, $branchDeployed,
        $brand, $empStatus, $subStatus, $agency, $from, $to
    ] = array_pad($row, 15, '');

    $rejectedExport[] = [
        'last_name'          => clean($lastName),
        'first_name'         => clean($firstName),
        'middle_name'        => clean($mi),
        'suffix'             => clean($suffix),
        'gender'             => mb_strtoupper(clean($gender), 'UTF-8'),
        'birthday'           => toSqlDate($birthday) ?? clean($birthday),
        'branch'             => $branchCode ?: strtoupper(clean($branch)),
        'brand'              => $brandNorm  ?: mb_strtoupper(clean($brand), 'UTF-8'),
        'employment_status'  => $employmentStatus ?: mb_strtoupper(clean($empStatus), 'UTF-8'),
        'sub_status'         => $subStatusNorm ?: mb_strtoupper(clean($subStatus), 'UTF-8'),
        'agency'             => $agencyNorm ?: mb_strtoupper(clean($agency), 'UTF-8'),
        'date_hired'         => toSqlDate($dateHired) ?? clean($dateHired),
        'reason'             => $reason,
    ];

    $slotErrors[] = ['row' => implode(', ', $row), 'reason' => $reason];
};

foreach ($parsedRows as $row) {

    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) {
        $skipped++;
        continue;
    }

    while (count($row) < 15) $row[] = '';

    [
        $branch, $lastName, $firstName, $mi, $suffix,
        $gender, $birthday, $dateHired, $branchDeployed,
        $brand, $employmentStatus, $subStatus, $agency, $from, $to
    ] = $row;

    $subStatusNorm   = mb_strtoupper(clean($subStatus),       'UTF-8');
    $brandNorm       = mb_strtoupper(clean($brand),           'UTF-8');
    $agencyNorm      = mb_strtoupper(clean($agency),          'UTF-8');
    $empStatusNorm   = mb_strtoupper(clean($employmentStatus),'UTF-8');
    $branchKey       = strtoupper(clean($branch));
    $branchCode      = $branchMap[$branchKey] ?? null;

    // Branch check
    if ($branchCode === null && $branchKey !== '') {
        $reject($row, "Branch '{$branchKey}' not found in branches table.");
        continue;
    }

    // Duplicate check
    $dupKey = mb_strtoupper(clean($lastName),  'UTF-8') . '|' .
              mb_strtoupper(clean($firstName), 'UTF-8') . '|' .
              (toSqlDate($birthday) ?? '')               . '|' .
              strtoupper($branchCode ?? '')               . '|' .
              $brandNorm;
    if (isset($existingSet[$dupKey])) {
        $duplicates[] = implode(', ', $row);
        $rejectedExport[] = [
            'last_name'         => clean($lastName),
            'first_name'        => clean($firstName),
            'middle_name'       => clean($mi),
            'suffix'            => clean($suffix),
            'gender'            => mb_strtoupper(clean($gender), 'UTF-8'),
            'birthday'          => toSqlDate($birthday) ?? clean($birthday),
            'branch'            => $branchCode,
            'brand'             => $brandNorm,
            'employment_status' => $empStatusNorm,
            'sub_status'        => $subStatusNorm,
            'agency'            => $agencyNorm,
            'date_hired'        => toSqlDate($dateHired) ?? clean($dateHired),
            'reason'            => 'Duplicate — already exists in employee_info',
        ];
        continue;
    }

    // Agency check
    if ($agencyNorm !== '' && !isset($agencySet[$agencyNorm])) {
        $reject($row, "Agency '{$agencyNorm}' not found in agencies table.", $branchCode, $brandNorm, $agencyNorm, $subStatusNorm, $empStatusNorm);
        continue;
    }

    // Slot check
    [$valid, $reason] = claimAssignmentSlot($branchCode, $brandNorm, $assignmentMap, $consumed);
    if (!$valid) {
        $reject($row, $reason, $branchCode, $brandNorm, $agencyNorm, $subStatusNorm, $empStatusNorm);
        continue;
    }

    // Group IDs
    $employeeId        = resolveGroupId($lastName, $firstName, 'EMP', $employeeIdMap);
    $rovingGroupId     = null;
    $multiBrandGroupId = null;
    if (in_array($subStatusNorm, ['MULTI BRANCH', 'HYBRID'])) {
        $rovingGroupId = resolveGroupId($lastName, $firstName, 'ROV', $rovingGroupIdMap);
    }
    if (in_array($subStatusNorm, ['MULTI BRAND', 'HYBRID'])) {
        $multiBrandGroupId = resolveGroupId($lastName, $firstName, 'MBR', $multiBrandGroupIdMap);
    }

    $params = [
        ':first_name'           => clean($firstName)      ?: null,
        ':last_name'            => clean($lastName)       ?: null,
        ':middle_name'          => clean($mi)             ?: null,
        ':suffix'               => clean($suffix)         ?: null,
        ':gender'               => mb_strtoupper(clean($gender), 'UTF-8') ?: null,
        ':birthday'             => toSqlDate($birthday),
        ':branch'               => $branchCode,
        ':brand'                => $brandNorm             ?: null,
        ':employment_status'    => $empStatusNorm         ?: null,
        ':sub_status'           => $subStatusNorm         ?: null,
        ':agency'               => $agencyNorm            ?: null,
        ':date_hired'           => toSqlDate($dateHired),
        ':start_date'           => toSqlDate($from),
        ':end_date'             => toSqlDate($to),
        ':remarks'              => clean($branchDeployed) ?: null,
        ':first_remark'         => clean($branchDeployed) ?: null,
        ':employee_id'          => $employeeId,
        ':roving_group_id'      => $rovingGroupId,
        ':multi_brand_group_id' => $multiBrandGroupId,
    ];

    try {
        $stmt->execute($params);
        $count++;

        // Register this row so any identical row later in the CSV is caught as a duplicate
        $existingSet[$dupKey] = true;

        // Accumulate branches + brands per employee for deferred history write
        if (!isset($historyData[$employeeId])) {
            $historyData[$employeeId] = [
                'date_hired'        => toSqlDate($dateHired),
                'employment_status' => $empStatusNorm,
                'sub_status'        => $subStatusNorm,
                'remarks'           => clean($branchDeployed) ?: null,
                'branches'          => [],
                'brands'            => [],
            ];
        }
        $branchName = $branchNameMap[strtoupper($branchCode)] ?? $branchCode;
        $historyData[$employeeId]['branches'][$branchName] = true; // key = distinct
        $historyData[$employeeId]['brands'][$brandNorm]    = true;

    } catch (PDOException $e) {
        $errors[] = ['row' => implode(', ', $row), 'error' => $e->getMessage()];
    }
}

// ── Write history (deferred so non-STATIONARY employees get all branches/brands) ──

foreach ($historyData as $employeeId => $h) {
    $dateHiredFmt = $h['date_hired']
        ? date('m/d/Y', strtotime($h['date_hired']))
        : 'N/A';

    $branches = implode(', ', array_keys($h['branches']));
    $brands   = implode(', ', array_keys($h['brands']));

    if ($h['sub_status'] === 'STATIONARY') {
        // Single branch + brand — match existing stored proc format exactly
        $reasonForUpdate =
            'ASSIGNED | Date Hired: '   . $dateHiredFmt .
            ' | Employment Status: '    . $h['employment_status'] .
            ' | Sub-Status: '           . $h['sub_status'] .
            ' | Branch: '               . $branches .
            ' Brand: '                  . $brands;
    } else {
        // Non-stationary: list all distinct branches and brands
        $reasonForUpdate =
            'ASSIGNED | Date Hired: '   . $dateHiredFmt .
            ' | Employment Status: '    . $h['employment_status'] .
            ' | Sub-Status: '           . $h['sub_status'] .
            ' | Branches: '             . $branches .
            ' | Brands: '               . $brands;
    }

    try {
        $historyStmt->execute([
            ':employee_id'       => $employeeId,
            ':reason_for_update' => $reasonForUpdate,
            ':remarks'           => $h['remarks'],
        ]);
    } catch (PDOException $e) {
        $errors[] = ['row' => "History for {$employeeId}", 'error' => $e->getMessage()];
    }
}

// ── Exports (run after both passes so all data is available) ──────────────────

$exportType = $_GET['export'] ?? '';

if ($exportType === 'slots') {

    // Recalculate slot needs for export (missing + short only)
    $exportRows = [];
    foreach ($slotNeeds as $key => $info) {
        $available = $assignmentMap[$key]['available'] ?? 0;
        $exists    = isset($assignmentMap[$key]);
        $needed    = $info['needed'];

        if (!$exists || $available < $needed) {
            $required = $exists
                ? $assignmentMap[$key]['assigned'] + $needed
                : $needed;
            $exportRows[] = [$info['branch'], $info['brand'], $required];
        }
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="needed_assignments_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['branch_name', 'brand_name', 'required_count']);
    foreach ($exportRows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

if ($exportType === 'rejected') {

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rejected_employees_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, [
        'Last Name', 'First Name', 'Middle Name', 'Suffix', 'Gender',
        'Birthday', 'Branch', 'Brand', 'Employment Status', 'Sub Status',
        'Agency', 'Date Hired', 'Reason'
    ]);
    foreach ($rejectedExport as $r) {
        fputcsv($out, [
            $r['last_name'], $r['first_name'], $r['middle_name'], $r['suffix'], $r['gender'],
            $r['birthday'],  $r['branch'],     $r['brand'],       $r['employment_status'],
            $r['sub_status'],$r['agency'],     $r['date_hired'],  $r['reason'],
        ]);
    }
    fclose($out);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Import</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<h4>Pre-flight Check</h4>

<?php if ($preflightPassed): ?>
    <div class="alert alert-success">✅ All checks passed.</div>
<?php else: ?>
    <div class="alert alert-warning">⚠️ Some issues found below — rows that meet all requirements were still imported.</div>
<?php endif; ?>

<h5 class="mt-4">Agencies</h5>
<table class="table table-sm table-bordered">
    <thead class="table-dark">
        <tr><th>Agency</th><th class="text-center">Employees in CSV</th><th class="text-center">Status</th></tr>
    </thead>
    <tbody>
        <?php foreach ($agenciesOk as $name => $c): ?>
        <tr class="table-success">
            <td><?= htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td class="text-center"><?= $c ?></td>
            <td class="text-center">✅ Found</td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($agenciesMissing as $name => $c): ?>
        <tr class="table-danger">
            <td><?= htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td class="text-center"><?= $c ?></td>
            <td class="text-center">❌ Missing — add to <code>agencies</code> table</td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($agencyNeeds)): ?>
        <tr><td colspan="3" class="text-muted">No agencies in CSV.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<h5 class="mt-4 d-flex align-items-center gap-3">
    Plantillas (Assignment Slots)
    <?php if (!empty($slotsMissing) || !empty($slotsShort)): ?>
    <a href="?export=slots" class="btn btn-sm btn-outline-secondary">⬇️ Download Missing as CSV</a>
    <?php endif; ?>
</h5>
<table class="table table-sm table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Branch</th><th>Brand</th>
            <th class="text-center">Needed</th>
            <th class="text-center">Required</th>
            <th class="text-center">Already Assigned</th>
            <th class="text-center">Available</th>
            <th class="text-center">Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($slotsOk as $info): ?>
        <tr class="table-success">
            <td><?= htmlspecialchars($info['branch'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($info['brand'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')  ?></td>
            <td class="text-center"><?= $info['needed']    ?></td>
            <td class="text-center"><?= $info['required']  ?></td>
            <td class="text-center"><?= $info['assigned']  ?></td>
            <td class="text-center"><?= $info['available'] ?></td>
            <td class="text-center">✅ OK</td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($slotsShort as $info): ?>
        <tr class="table-warning">
            <td><?= htmlspecialchars($info['branch'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($info['brand'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')  ?></td>
            <td class="text-center"><?= $info['needed']    ?></td>
            <td class="text-center"><?= $info['required']  ?></td>
            <td class="text-center"><?= $info['assigned']  ?></td>
            <td class="text-center"><?= $info['available'] ?></td>
            <td class="text-center">⚠️ Needs <?= $info['needed'] - $info['available'] ?> more slot(s)</td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($slotsMissing as $info): ?>
        <tr class="table-danger">
            <td><?= htmlspecialchars($info['branch'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($info['brand'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')  ?></td>
            <td class="text-center"><?= $info['needed'] ?></td>
            <td class="text-center">—</td>
            <td class="text-center">—</td>
            <td class="text-center">—</td>
            <td class="text-center">❌ No setup — add to <code>assignment</code> table</td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($slotNeeds)): ?>
        <tr><td colspan="7" class="text-muted">No slot needs detected.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<hr>
<h4 class="d-flex align-items-center gap-3">
    Import Result
    <button onclick="exportResultsCsv()" class="btn btn-sm btn-outline-secondary">⬇️ Download Results as CSV</button>
    <?php if (!empty($rejectedExport)): ?>
    <a href="?export=rejected" class="btn btn-sm btn-outline-danger">⬇️ Download Rejected as CSV</a>
    <?php endif; ?>
</h4>
<ul id="import-summary">
    <li>✅ <strong><?= $count ?></strong> row(s) inserted successfully.</li>
    <li>⏭️ <strong><?= $skipped ?></strong> blank row(s) skipped.</li>
    <li>🔁 <strong><?= count($duplicates) ?></strong> row(s) skipped as exact duplicates.</li>
    <li>🚫 <strong><?= count($slotErrors) ?></strong> row(s) rejected (branch / agency / slot).</li>
    <li>❌ <strong><?= count($errors) ?></strong> database error(s).</li>
    <li>🪪 <strong><?= count($employeeIdMap) ?></strong> unique employee ID(s) generated.</li>
    <li>🔀 <strong><?= count($rovingGroupIdMap) ?></strong> roving group ID(s) generated.</li>
    <li>🏷️ <strong><?= count($multiBrandGroupIdMap) ?></strong> multi-brand group ID(s) generated.</li>
</ul>

<?php if ($duplicates): ?>
<h5 class="text-secondary">🔁 Skipped — Exact Duplicates</h5>
<table class="table table-sm table-bordered table-secondary result-table">
    <thead><tr><th>Row Data</th></tr></thead>
    <tbody>
        <?php foreach ($duplicates as $d): ?>
        <tr><td><small><?= htmlspecialchars($d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small></td></tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($slotErrors): ?>
<h5 class="text-warning">🚫 Rejected During Import</h5>
<table class="table table-sm table-bordered table-warning result-table">
    <thead><tr><th>Row Data</th><th>Reason</th></tr></thead>
    <tbody>
        <?php foreach ($slotErrors as $e): ?>
        <tr>
            <td><small><?= htmlspecialchars($e['row'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small></td>
            <td><small><?= htmlspecialchars($e['reason'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($errors): ?>
<h5 class="text-danger">❌ Database Errors</h5>
<table class="table table-sm table-bordered table-striped result-table">
    <thead><tr><th>Row Data</th><th>Error</th></tr></thead>
    <tbody>
        <?php foreach ($errors as $e): ?>
        <tr>
            <td><small><?= htmlspecialchars($e['row'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small></td>
            <td><small class="text-danger"><?= htmlspecialchars($e['error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="alert alert-danger mt-3">
    ⚠️ Delete or move <code>import_employees.php</code> from your server now that the import is done.
</div>

<script>
function exportResultsCsv() {
    const rows = [];
    const now  = new Date().toLocaleString();

    // ── Summary ───────────────────────────────────────────────────────────────
    rows.push(['IMPORT RESULTS — ' + now]);
    rows.push([]);
    document.querySelectorAll('#import-summary li').forEach(li => {
        rows.push([li.innerText]);
    });
    rows.push([]);

    // ── Tables ────────────────────────────────────────────────────────────────
    document.querySelectorAll('.result-table').forEach(table => {
        const heading = table.previousElementSibling?.innerText ?? '';
        rows.push([heading]);

        table.querySelectorAll('tr').forEach(tr => {
            const cells = [...tr.querySelectorAll('th, td')].map(td => td.innerText.trim());
            rows.push(cells);
        });
        rows.push([]);
    });

    // ── Encode & download ─────────────────────────────────────────────────────
    const csv     = rows.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\r\n');
    const bom     = '\uFEFF'; // UTF-8 BOM so Excel opens Ñ correctly
    const blob    = new Blob([bom + csv], { type: 'text/csv;charset=utf-8;' });
    const url     = URL.createObjectURL(blob);
    const link    = document.createElement('a');
    const date    = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    link.href     = url;
    link.download = 'import_results_' + date + '.csv';
    link.click();
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>