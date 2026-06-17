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
    return array_map(fn($v) => mb_convert_encoding($v, 'UTF-8', 'Windows-1252'), $row);
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
    $available = $assignmentMap[$key]['available'];
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

// ── CSV Export (run scan then output CSV, skip HTML) ─────────────────────────
// Access via: import_employees.php?export=slots

if (($_GET['export'] ?? '') === 'slots') {

    // Run the scan inline
    $scanHandle = fopen($csvFile, 'r');
    fgetcsv($scanHandle); // skip header
    $exportSlotNeeds = [];

    while (($row = fgetcsv($scanHandle)) !== false) {
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

        if ($branchCode === null || $brandNorm === '') continue;

        $dupKey = mb_strtoupper(clean($lastName),  'UTF-8') . '|' .
                  mb_strtoupper(clean($firstName), 'UTF-8') . '|' .
                  (toSqlDate($birthday) ?? '')               . '|' .
                  strtoupper($branchCode)                    . '|' .
                  $brandNorm;

        if (isset($existingSet[$dupKey])) continue; // skip duplicates

        $slotKey = strtoupper($branchCode) . '|' . $brandNorm;
        if (!isset($exportSlotNeeds[$slotKey])) {
            $exportSlotNeeds[$slotKey] = ['branch' => $branchCode, 'brand' => $brandNorm, 'needed' => 0];
        }
        $exportSlotNeeds[$slotKey]['needed']++;
    }
    fclose($scanHandle);

    // Only export slots that are missing or short
    $exportRows = [];
    foreach ($exportSlotNeeds as $key => $info) {
        $available = $assignmentMap[$key]['available'] ?? 0;
        $exists    = isset($assignmentMap[$key]);
        $needed    = $info['needed'];

        if (!$exists || $available < $needed) {
            $required = $exists
                ? $assignmentMap[$key]['assigned'] + $needed   // current assigned + what we still need
                : $needed;                                      // no setup yet — required = needed

            $exportRows[] = [$info['branch'], $info['brand'], $required];
        }
    }

    // Output CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="needed_assignments_' . date('Ymd') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['branch_name', 'brand_name', 'required_count']); // header
    foreach ($exportRows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// ── PASS 1: Scan CSV — collect needs, parse rows ──────────────────────────────

$handle = fopen($csvFile, 'r');
if (!$handle) die("Cannot open CSV.");
fgetcsv($handle); // skip header

$parsedRows   = []; // all valid non-blank rows for pass 2
$agencyNeeds  = []; // UPPER(agency) → count needed from CSV
$slotNeeds    = []; // BRANCHCODE|BRAND → ['label' => '...', 'needed' => N]

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

    // Collect agency needs
    if ($agencyNorm !== '') {
        $agencyNeeds[$agencyNorm] = ($agencyNeeds[$agencyNorm] ?? 0) + 1;
    }

    // Collect slot needs (only if branch resolved, agency valid, and not a duplicate)
    if ($branchCode !== null && $brandNorm !== '' && isset($agencySet[$agencyNorm])) {
        $dupKey = mb_strtoupper(clean($lastName),  'UTF-8') . '|' .
                  mb_strtoupper(clean($firstName), 'UTF-8') . '|' .
                  (toSqlDate($birthday) ?? '')               . '|' .
                  strtoupper($branchCode)                    . '|' .
                  $brandNorm;

        if (!isset($existingSet[$dupKey])) {
            $slotKey = strtoupper($branchCode) . '|' . $brandNorm;
            if (!isset($slotNeeds[$slotKey])) {
                $slotNeeds[$slotKey] = [
                    'branch' => $branchCode,
                    'brand'  => $brandNorm,
                    'needed' => 0,
                ];
            }
            $slotNeeds[$slotKey]['needed']++;
        }
    }

    $parsedRows[] = $row;
}
fclose($handle);

// ── Pre-flight analysis ───────────────────────────────────────────────────────

// Agencies: split into found vs missing
$agenciesOk      = [];
$agenciesMissing = [];
foreach ($agencyNeeds as $name => $count) {
    if (isset($agencySet[$name])) {
        $agenciesOk[$name] = $count;
    } else {
        $agenciesMissing[$name] = $count;
    }
}

// Slots: categorize each needed slot
$slotsOk      = []; // exists and has enough capacity
$slotsShort   = []; // exists but not enough capacity
$slotsMissing = []; // no setup at all
foreach ($slotNeeds as $key => $info) {
    if (!isset($assignmentMap[$key])) {
        $slotsMissing[$key] = $info;
    } else {
        $available = $assignmentMap[$key]['available'];
        $needed    = $info['needed'];
        $info['available'] = $available;
        $info['required']  = $assignmentMap[$key]['required'];
        $info['assigned']  = $assignmentMap[$key]['assigned'];
        if ($available >= $needed) {
            $slotsOk[$key] = $info;
        } else {
            $slotsShort[$key] = $info;
        }
    }
}

$preflightPassed = empty($agenciesMissing) && empty($slotsMissing) && empty($slotsShort);
// Note: import always runs regardless — pre-flight is informational only.

// ── PASS 2: Import ────────────────────────────────────────────────────────────

$errors        = [];
$slotErrors    = [];
$duplicates    = [];
$count         = 0;
$skipped       = 0;
$consumed      = [];
$employeeIdMap        = [];
$rovingGroupIdMap     = [];
$multiBrandGroupIdMap = [];

$sql = "
    INSERT INTO [IPROM].[dbo].[employee_info] (
        [first_name],[last_name],[middle_name],[suffix],
        [gender],[birthday],[branch],[brand],
        [employment_status],[sub_status],[agency],
        [date_hired],[start_date],[end_date],[remarks], [first_remark],
        [employee_id],[roving_group_id],[multi_brand_group_id],
        [status],[hidden],[created_at],[updated_at],
        [assignment_date],[last_assigned_by],[last_updated_by]
    ) VALUES (
        :first_name,:last_name,:middle_name,:suffix,
        :gender,:birthday,:branch,:brand,
        :employment_status,:sub_status,:agency,
        :date_hired,:start_date,:end_date,:remarks, :first_remark,
        :employee_id,:roving_group_id,:multi_brand_group_id,
        'ACTIVE',0,GETDATE(),GETDATE(),
        GETDATE(),'SYSTEM','SYSTEM'
    )
";
$stmt = $pdo->prepare($sql);

$historyInserted = []; // tracks employee_ids that already have a history row this session

$historySql = "
    INSERT INTO [IPROM].[dbo].[employee_reason_history] (
        [employee_id],
        [reason_for_update],
        [update_date],
        [remarks],
        [updated_by]
    ) VALUES (
        :employee_id,
        :reason_for_update,
        GETDATE(),
        :remarks,
        'SYSTEM'
    )
";
$historyStmt = $pdo->prepare($historySql);

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

        $subStatusNorm = mb_strtoupper(clean($subStatus), 'UTF-8');
        $brandNorm     = mb_strtoupper(clean($brand),     'UTF-8');
        $agencyNorm    = mb_strtoupper(clean($agency),    'UTF-8');
        $branchKey     = strtoupper(clean($branch));
        $branchCode    = $branchMap[$branchKey] ?? null;

        // Skip if branch still unresolved
        if ($branchCode === null && $branchKey !== '') {
            $slotErrors[] = ['row' => implode(', ', $row), 'reason' => "Branch '{$branchKey}' not found."];
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
            continue;
        }

        // Agency check
        if ($agencyNorm !== '' && !isset($agencySet[$agencyNorm])) {
            $slotErrors[] = ['row' => implode(', ', $row), 'reason' => "Agency '{$agencyNorm}' not found."];
            continue;
        }

        // Slot check
        [$valid, $reason] = claimAssignmentSlot($branchCode, $brandNorm, $assignmentMap, $consumed);
        if (!$valid) {
            $slotErrors[] = ['row' => implode(', ', $row), 'reason' => $reason];
            continue;
        }

        // Group IDs
        $employeeId = resolveGroupId($lastName, $firstName, 'EMP', $employeeIdMap);
        $rovingGroupId = null;
        if (in_array($subStatusNorm, ['MULTI BRANCH', 'HYBRID'])) {
            $rovingGroupId = resolveGroupId($lastName, $firstName, 'ROV', $rovingGroupIdMap);
        }
        $multiBrandGroupId = null;
        if (in_array($subStatusNorm, ['MULTI BRAND', 'HYBRID'])) {
            $multiBrandGroupId = resolveGroupId($lastName, $firstName, 'MBR', $multiBrandGroupIdMap);
        }

        $params = [
            ':first_name'           => clean($firstName)                          ?: null,
            ':last_name'            => clean($lastName)                           ?: null,
            ':middle_name'          => clean($mi)                                 ?: null,
            ':suffix'               => clean($suffix)                             ?: null,
            ':gender'               => mb_strtoupper(clean($gender), 'UTF-8')     ?: null,
            ':birthday'             => toSqlDate($birthday),
            ':branch'               => $branchCode,
            ':brand'                => $brandNorm                                 ?: null,
            ':employment_status'    => mb_strtoupper(clean($employmentStatus), 'UTF-8') ?: null,
            ':sub_status'           => $subStatusNorm                             ?: null,
            ':agency'               => $agencyNorm                                ?: null,
            ':date_hired'           => toSqlDate($dateHired),
            ':start_date'           => toSqlDate($from),
            ':end_date'             => toSqlDate($to),
            ':remarks'              => clean($branchDeployed)                     ?: null,
            ':first_remark'         => clean($branchDeployed)                     ?: null, // for easier querying
            ':employee_id'          => $employeeId,
            ':roving_group_id'      => $rovingGroupId,
            ':multi_brand_group_id' => $multiBrandGroupId,
        ];

        try {
            $stmt->execute($params);
            $count++;

            // Insert history once per employee_id (mirrors IF NOT EXISTS in stored proc)
            if (!isset($historyInserted[$employeeId])) {
                $branchName    = $branchNameMap[strtoupper($branchCode)] ?? $branchCode;
                $dateHiredFmt  = toSqlDate($dateHired)
                    ? date('m/d/Y', strtotime(toSqlDate($dateHired)))
                    : 'N/A';

                $reasonForUpdate =
                    'ASSIGNED | Date Hired: ' . $dateHiredFmt .
                    ' | Employment Status: '  . mb_strtoupper(clean($employmentStatus), 'UTF-8') .
                    ' | Sub-Status: '         . $subStatusNorm .
                    ' | Branch: '             . $branchName .
                    ' Brand: '                . $brandNorm;

                $historyStmt->execute([
                    ':employee_id'       => $employeeId,
                    ':reason_for_update' => $reasonForUpdate,
                    ':remarks'           => clean($branchDeployed) ?: null,
                ]);

                $historyInserted[$employeeId] = true;
            }

        } catch (PDOException $e) {
            $errors[] = ['row' => implode(', ', $row), 'error' => $e->getMessage()];
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Import</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>
<body class="p-4">

<h4>Pre-flight Check</h4>

<?php if ($preflightPassed): ?>
    <div class="alert alert-success">✅ All checks passed.</div>
<?php else: ?>
    <div class="alert alert-warning">⚠️ Some issues found below — rows that meet all requirements were still imported.</div>
<?php endif; ?>

<?php /* ── Agencies ── */ ?>
<h5 class="mt-4">Agencies</h5>
<table class="table table-sm table-bordered">
    <thead class="table-dark">
        <tr><th>Agency</th><th class="text-center">Employees in CSV</th><th class="text-center">Status</th></tr>
    </thead>
    <tbody>
        <?php foreach ($agenciesOk as $name => $count): ?>
        <tr class="table-success">
            <td><?= htmlspecialchars($name) ?></td>
            <td class="text-center"><?= $count ?></td>
            <td class="text-center">✅ Found</td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($agenciesMissing as $name => $count): ?>
        <tr class="table-danger">
            <td><?= htmlspecialchars($name) ?></td>
            <td class="text-center"><?= $count ?></td>
            <td class="text-center">❌ Missing — add to <code>agencies</code> table</td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($agencyNeeds)): ?>
        <tr><td colspan="3" class="text-muted">No agencies in CSV.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php /* ── Plantillas (assignment slots) ── */ ?>
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
            <td><?= htmlspecialchars($info['branch']) ?></td>
            <td><?= htmlspecialchars($info['brand'])  ?></td>
            <td class="text-center"><?= $info['needed']    ?></td>
            <td class="text-center"><?= $info['required']  ?></td>
            <td class="text-center"><?= $info['assigned']  ?></td>
            <td class="text-center"><?= $info['available'] ?></td>
            <td class="text-center">✅ OK</td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($slotsShort as $info): ?>
        <tr class="table-warning">
            <td><?= htmlspecialchars($info['branch']) ?></td>
            <td><?= htmlspecialchars($info['brand'])  ?></td>
            <td class="text-center"><?= $info['needed']    ?></td>
            <td class="text-center"><?= $info['required']  ?></td>
            <td class="text-center"><?= $info['assigned']  ?></td>
            <td class="text-center"><?= $info['available'] ?></td>
            <td class="text-center">⚠️ Not enough — needs <?= $info['needed'] - $info['available'] ?> more slot(s)</td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($slotsMissing as $info): ?>
        <tr class="table-danger">
            <td><?= htmlspecialchars($info['branch']) ?></td>
            <td><?= htmlspecialchars($info['brand'])  ?></td>
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
    <button class="btn btn-sm btn-success" onclick="exportResultsToExcel()">⬇️ Export to Excel</button>
</h4>
<ul>
    <li>✅ <strong><?= $count ?></strong> row(s) inserted successfully.</li>
    <li>⏭️ <strong><?= $skipped ?></strong> blank row(s) skipped.</li>
    <li>🔁 <strong><?= count($duplicates) ?></strong> row(s) skipped as exact duplicates.</li>
    <li>🚫 <strong><?= count($slotErrors) ?></strong> row(s) rejected (agency / slot / branch).</li>
    <li>❌ <strong><?= count($errors) ?></strong> database error(s).</li>
    <li>🪪 <strong><?= count($employeeIdMap) ?></strong> unique employee ID(s) generated.</li>
    <li>🔀 <strong><?= count($rovingGroupIdMap) ?></strong> roving group ID(s) generated.</li>
    <li>🏷️ <strong><?= count($multiBrandGroupIdMap) ?></strong> multi-brand group ID(s) generated.</li>
</ul>

<?php if ($duplicates): ?>
<h5 class="text-secondary">🔁 Skipped — Exact Duplicates</h5>
<table id="tbl-duplicates" class="table table-sm table-bordered table-secondary">
    <thead><tr><th>Row Data</th></tr></thead>
    <tbody>
        <?php foreach ($duplicates as $d): ?>
        <tr><td><small><?= htmlspecialchars($d) ?></small></td></tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($slotErrors): ?>
<h5 class="text-warning">🚫 Rejected During Import</h5>
<table id="tbl-slot-errors" class="table table-sm table-bordered table-warning">
    <thead><tr><th>Row Data</th><th>Reason</th></tr></thead>
    <tbody>
        <?php foreach ($slotErrors as $e): ?>
        <tr>
            <td><small><?= htmlspecialchars($e['row']) ?></small></td>
            <td><small><?= htmlspecialchars($e['reason']) ?></small></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($errors): ?>
<h5 class="text-danger">❌ Database Errors</h5>
<table id="tbl-db-errors" class="table table-sm table-bordered table-striped">
    <thead><tr><th>Row Data</th><th>Error</th></tr></thead>
    <tbody>
        <?php foreach ($errors as $e): ?>
        <tr>
            <td><small><?= htmlspecialchars($e['row']) ?></small></td>
            <td><small class="text-danger"><?= htmlspecialchars($e['error']) ?></small></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<script>
function exportResultsToExcel() {
    const wb = XLSX.utils.book_new();
    const date = new Date().toISOString().slice(0, 10);

    // Summary sheet
    const summaryData = [
        ['Metric', 'Count'],
        ['Inserted successfully',            <?= $count ?>],
        ['Blank rows skipped',               <?= $skipped ?>],
        ['Skipped — exact duplicates',       <?= count($duplicates) ?>],
        ['Rejected (agency/slot/branch)',     <?= count($slotErrors) ?>],
        ['Database errors',                  <?= count($errors) ?>],
        ['Unique employee IDs generated',    <?= count($employeeIdMap) ?>],
        ['Roving group IDs generated',       <?= count($rovingGroupIdMap) ?>],
        ['Multi-brand group IDs generated',  <?= count($multiBrandGroupIdMap) ?>],
    ];
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(summaryData), 'Summary');

    // Table helper — reads any <table> by id into a sheet
    function sheetFromTable(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        return XLSX.utils.table_to_sheet(el, { raw: false });
    }

    const dupSheet   = sheetFromTable('tbl-duplicates');
    const slotSheet  = sheetFromTable('tbl-slot-errors');
    const errSheet   = sheetFromTable('tbl-db-errors');

    if (dupSheet)  XLSX.utils.book_append_sheet(wb, dupSheet,  'Duplicates');
    if (slotSheet) XLSX.utils.book_append_sheet(wb, slotSheet, 'Rejected');
    if (errSheet)  XLSX.utils.book_append_sheet(wb, errSheet,  'DB Errors');

    XLSX.writeFile(wb, `import_result_${date}.xlsx`);
}
</script>

<div class="alert alert-danger mt-3">
    ⚠️ Delete or move <code>import_employees.php</code> from your server now that the import is done.
</div>

</body>
</html>