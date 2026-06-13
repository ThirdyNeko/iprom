<?php
/**
 * import_employees.php
 * One-time CSV import into IPROM [dbo].[employee_info]
 * Place this file in your IPROM root (same level as db.php), then open in browser.
 * DELETE or MOVE this file after import is done.
 */

include_once 'db.php'; // uses qa_db() pattern

$csvFile = __DIR__ . '/Sample_Promo_data_Sheet1_.csv';

if (!file_exists($csvFile)) {
    die("CSV file not found: $csvFile");
}

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

function resolveGroupId(string $lastName, string $firstName, string $birthday, string $prefix, array &$map): string {
    $key = strtoupper(trim($lastName)) . '|' . strtoupper(trim($firstName)) . '|' . trim($birthday);
    if (!isset($map[$key])) {
        $map[$key] = generateId($prefix);
    }
    return $map[$key];
}

/**
 * Check if a branch+brand slot has capacity, accounting for rows
 * already consumed during this import session (DB assigned_count
 * won't update until after each insert, so we track in-memory).
 *
 * Returns true and increments the consumed counter if a slot is available.
 * Returns false with a reason string if not.
 */
function claimAssignmentSlot(
    string  $branchCode,
    string  $brand,
    array   &$assignmentMap,
    array   &$consumed
): array {
    $key = strtoupper($branchCode) . '|' . strtoupper($brand);

    // 1st spec: assignment must exist
    if (!isset($assignmentMap[$key])) {
        return [false, "No assignment setup found for {$branchCode} - {$brand}."];
    }

    $available = $assignmentMap[$key]['available'];
    $alreadyConsumed = $consumed[$key] ?? 0;

    // 2nd spec: remaining capacity must be > 0
    if (($available - $alreadyConsumed) <= 0) {
        return [false, "Slot is full for {$branchCode} - {$brand} (required: {$assignmentMap[$key]['required']}, assigned: {$assignmentMap[$key]['assigned']}, imported this session: {$alreadyConsumed})."];
    }

    // Claim the slot
    $consumed[$key] = $alreadyConsumed + 1;

    return [true, null];
}

// ── Connect ───────────────────────────────────────────────────────────────────

$pdo = qa_db();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

// ── Build branch lookup: UPPER(branch_name) → branch_code ────────────────────

$branchMap = [];
try {
    $rows = $pdo->query("SELECT [branch], [branch_code] FROM [IPROM].[dbo].[branches]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $branchMap[strtoupper(trim($r['branch']))] = $r['branch_code'];
    }
} catch (PDOException $e) {
    die("Failed to load branch lookup: " . $e->getMessage());
}

// ── Build assignment lookup: UPPER(branch_name)|UPPER(brand_name) → slot info ─
// NOTE: assignment.branch_name stores branch_code values (same as save_employee.php)

$assignmentMap = [];
try {
    $rows = $pdo->query("
        SELECT [branch_name], [brand_name], [required_count], [assigned_count]
        FROM [IPROM].[dbo].[assignment]
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $key = strtoupper(trim($r['branch_name'])) . '|' . strtoupper(trim($r['brand_name']));
        $assignmentMap[$key] = [
            'required'  => (int) $r['required_count'],
            'assigned'  => (int) $r['assigned_count'],
            'available' => (int) $r['required_count'] - (int) $r['assigned_count'],
        ];
    }
} catch (PDOException $e) {
    die("Failed to load assignment lookup: " . $e->getMessage());
}

// ── Build agency lookup: UPPER(agencies) → true ──────────────────────────────

$agencySet = [];
try {
    $rows = $pdo->query("SELECT [agencies] FROM [IPROM].[dbo].[agencies]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $agencySet[mb_strtoupper(trim($r['agencies']), 'UTF-8')] = true;
    }
} catch (PDOException $e) {
    die("Failed to load agency lookup: " . $e->getMessage());
}

// ── Build existing records set: to skip exact duplicates ─────────────────────
// Key: LASTNAME|FIRSTNAME|BIRTHDAY|BRANCH|BRAND

$existingSet = [];
try {
    $rows = $pdo->query("
        SELECT [last_name], [first_name], [birthday], [branch], [brand]
        FROM [IPROM].[dbo].[employee_info]
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $key = mb_strtoupper(trim($r['last_name']),  'UTF-8') . '|' .
               mb_strtoupper(trim($r['first_name']), 'UTF-8') . '|' .
               substr(trim($r['birthday'] ?? ''), 0, 10)      . '|' .
               strtoupper(trim($r['branch'] ?? ''))            . '|' .
               mb_strtoupper(trim($r['brand'] ?? ''), 'UTF-8');
        $existingSet[$key] = true;
    }
} catch (PDOException $e) {
    die("Failed to load existing records: " . $e->getMessage());
}

// ── Read CSV ──────────────────────────────────────────────────────────────────

$handle = fopen($csvFile, 'r');
if (!$handle) die("Cannot open CSV.");

fgetcsv($handle); // skip header

$errors        = [];
$slotErrors    = []; // rows rejected due to assignment rules
$duplicates    = []; // rows skipped as exact duplicates
$count         = 0;
$skipped       = 0;
$branchMissing = [];
$consumed      = []; // in-memory slot counter for this import session

$employeeIdMap        = [];
$rovingGroupIdMap     = [];
$multiBrandGroupIdMap = [];

$sql = "
    INSERT INTO [IPROM].[dbo].[employee_info] (
        [first_name], [last_name], [middle_name], [suffix],
        [gender], [birthday], [branch], [brand],
        [employment_status], [sub_status], [agency],
        [date_hired], [start_date], [end_date], [remarks],
        [employee_id], [roving_group_id], [multi_brand_group_id],
        [status], [hidden], [created_at], [updated_at],
        [assignment_date], [last_assigned_by], [last_updated_by]
    ) VALUES (
        :first_name, :last_name, :middle_name, :suffix,
        :gender, :birthday, :branch, :brand,
        :employment_status, :sub_status, :agency,
        :date_hired, :start_date, :end_date, :remarks,
        :employee_id, :roving_group_id, :multi_brand_group_id,
        'ACTIVE', 0, GETDATE(), GETDATE(),
        GETDATE(), 'SYSTEM', 'SYSTEM'
    )
";

$stmt = $pdo->prepare($sql);

while (($row = fgetcsv($handle)) !== false) {

    $row = toUtf8($row);

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
    $brandNorm     = mb_strtoupper(clean($brand), 'UTF-8');

    // ── Resolve branch → branch_code ─────────────────────────────────────────
    $branchKey  = strtoupper(clean($branch));
    $branchCode = $branchMap[$branchKey] ?? null;

    if ($branchCode === null && $branchKey !== '') {
        $branchMissing[$branchKey] = true;
        $slotErrors[] = [
            'row'    => implode(', ', $row),
            'reason' => "Branch '{$branchKey}' not found in branches table — cannot validate assignment slot.",
        ];
        continue; // can't validate or insert without a branch code
    }

    // ── Duplicate check ───────────────────────────────────────────────────────
    // Use $branchCode (not raw branch name) and toSqlDate() so the key matches
    // what is actually stored in employee_info (branch_code + YYYY-MM-DD).
    $dupKey = mb_strtoupper(clean($lastName),  'UTF-8') . '|' .
              mb_strtoupper(clean($firstName), 'UTF-8') . '|' .
              (toSqlDate($birthday) ?? '')               . '|' .
              strtoupper($branchCode ?? '')               . '|' .
              mb_strtoupper(clean($brand), 'UTF-8');

    if (isset($existingSet[$dupKey])) {
        $duplicates[] = implode(', ', $row);
        continue;
    }

    // ── Agency validation ─────────────────────────────────────────────────────
    $agencyNorm = mb_strtoupper(clean($agency), 'UTF-8');
    if ($agencyNorm !== '' && !isset($agencySet[strtoupper($agencyNorm)])) {
        $slotErrors[] = [
            'row'    => implode(', ', $row),
            'reason' => "Agency '{$agencyNorm}' not found in agencies table.",
        ];
        continue;
    }

    // ── Assignment slot validation ────────────────────────────────────────────
    [$valid, $reason] = claimAssignmentSlot($branchCode, $brandNorm, $assignmentMap, $consumed);

    if (!$valid) {
        $slotErrors[] = ['row' => implode(', ', $row), 'reason' => $reason];
        continue;
    }

    // ── Resolve group IDs ─────────────────────────────────────────────────────
    $employeeId = resolveGroupId($lastName, $firstName, $birthday, 'EMP', $employeeIdMap);

    $rovingGroupId = null;
    if (in_array($subStatusNorm, ['MULTI BRANCH', 'HYBRID'])) {
        $rovingGroupId = resolveGroupId($lastName, $firstName, $birthday, 'ROV', $rovingGroupIdMap);
    }

    $multiBrandGroupId = null;
    if (in_array($subStatusNorm, ['MULTI BRAND', 'HYBRID'])) {
        $multiBrandGroupId = resolveGroupId($lastName, $firstName, $birthday, 'MBR', $multiBrandGroupIdMap);
    }

    // ── Insert ────────────────────────────────────────────────────────────────
    $params = [
        ':first_name'           => clean($firstName)          ?: null,
        ':last_name'            => clean($lastName)           ?: null,
        ':middle_name'          => clean($mi)                 ?: null,
        ':suffix'               => clean($suffix)             ?: null,
        ':gender'               => mb_strtoupper(clean($gender), 'UTF-8') ?: null,
        ':birthday'             => toSqlDate($birthday),
        ':branch'               => $branchCode,
        ':brand'                => $brandNorm                 ?: null,
        ':employment_status'    => mb_strtoupper(clean($employmentStatus), 'UTF-8') ?: null,
        ':sub_status'           => $subStatusNorm             ?: null,
        ':agency'               => $agencyNorm                 ?: null,
        ':date_hired'           => toSqlDate($dateHired),
        ':start_date'           => toSqlDate($from),
        ':end_date'             => toSqlDate($to),
        ':remarks'              => clean($branchDeployed)     ?: null,
        ':employee_id'          => $employeeId,
        ':roving_group_id'      => $rovingGroupId,
        ':multi_brand_group_id' => $multiBrandGroupId,
    ];

    try {
        $stmt->execute($params);
        $count++;
    } catch (PDOException $e) {
        $errors[] = [
            'row'   => implode(', ', $row),
            'error' => $e->getMessage(),
        ];
    }
}

fclose($handle);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Import Result</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
    <h4>Import Result</h4>
    <ul>
        <li>✅ <strong><?= $count ?></strong> row(s) inserted successfully.</li>
        <li>⏭️ <strong><?= $skipped ?></strong> blank row(s) skipped.</li>
        <li>🔁 <strong><?= count($duplicates) ?></strong> row(s) skipped as exact duplicates.</li>
        <li>🚫 <strong><?= count($slotErrors) ?></strong> row(s) rejected by assignment rules.</li>
        <li>❌ <strong><?= count($errors) ?></strong> database error(s).</li>
        <li>⚠️ <strong><?= count($branchMissing) ?></strong> unmatched branch name(s).</li>
        <li>🪪 <strong><?= count($employeeIdMap) ?></strong> unique employee ID(s) generated.</li>
        <li>🔀 <strong><?= count($rovingGroupIdMap) ?></strong> roving group ID(s) generated.</li>
        <li>🏷️ <strong><?= count($multiBrandGroupIdMap) ?></strong> multi-brand group ID(s) generated.</li>
    </ul>

    <?php if ($duplicates): ?>
    <h5 class="text-secondary">🔁 Skipped — Exact Duplicates Already in Database</h5>
    <table class="table table-sm table-bordered table-secondary">
        <thead>
            <tr><th>Row Data</th></tr>
        </thead>
        <tbody>
            <?php foreach ($duplicates as $d): ?>
            <tr>
                <td><small><?= htmlspecialchars($d) ?></small></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($slotErrors): ?>
    <h5 class="text-warning">🚫 Rejected by Assignment Rules</h5>
    <table class="table table-sm table-bordered table-warning">
        <thead>
            <tr><th>Row Data</th><th>Reason</th></tr>
        </thead>
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

    <?php if ($branchMissing): ?>
    <div class="alert alert-warning">
        <strong>Branch names not found in <code>branches</code> table:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach (array_keys($branchMissing) as $b): ?>
                <li><code><?= htmlspecialchars($b) ?></code></li>
            <?php endforeach; ?>
        </ul>
        <small class="d-block mt-1">
            Add these to <code>[dbo].[branches]</code> with the correct <code>branch_code</code>,
            then re-run the import (after deleting the already-inserted rows).
        </small>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <h5 class="text-danger">❌ Database Errors</h5>
    <table class="table table-sm table-bordered table-striped">
        <thead>
            <tr><th>Row Data</th><th>Error</th></tr>
        </thead>
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

    <div class="alert alert-danger mt-3">
        ⚠️ Delete or move <code>import_employees.php</code> from your server now that the import is done.
    </div>
</body>
</html>