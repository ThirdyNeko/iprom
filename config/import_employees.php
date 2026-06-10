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

/**
 * Convert M/D/YYYY (or M/D/YY) → YYYY-MM-DD.
 * Returns null if blank or unparseable.
 */
function toSqlDate(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') return null;
    $ts = strtotime($raw);
    if ($ts === false) return null;
    return date('Y-m-d', $ts);
}

/**
 * Normalize a string: trim + collapse internal whitespace.
 */
function clean(string $val): string {
    return trim(preg_replace('/\s+/', ' ', $val));
}

/**
 * Convert an entire CSV row from Windows-1252 (Excel default) to UTF-8.
 * Fixes Ñ, ñ, and other accented characters saved by Excel on Windows.
 */
function toUtf8(array $row): array {
    return array_map(fn($v) => mb_convert_encoding($v, 'UTF-8', 'Windows-1252'), $row);
}

/**
 * Generate a collision-safe ID with a given prefix.
 * e.g. EMP-20250610-A3F92CD8 / ROV-20250610-A3F92CD8 / MBR-20250610-A3F92CD8
 */
function generateId(string $prefix): string {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
}

/**
 * Resolve a grouped ID (employee_id / roving_group_id / multi_brand_group_id).
 * Same person across multiple rows shares one ID.
 * Key: "LASTNAME|FIRSTNAME|BIRTHDAY"
 */
function resolveGroupId(string $lastName, string $firstName, string $birthday, string $prefix, array &$map): string {
    $key = strtoupper(trim($lastName)) . '|' . strtoupper(trim($firstName)) . '|' . trim($birthday);
    if (!isset($map[$key])) {
        $map[$key] = generateId($prefix);
    }
    return $map[$key];
}

// ── Build branch lookup: UPPER(branch_name) → branch_code ────────────────────

$pdo = qa_db();

// Force UTF-8 parameter encoding over ODBC.
// Without this, ODBC Driver 17 chokes on Ñ/ñ and other non-ASCII characters
// with "No mapping for the Unicode character exists in the target multi-byte code page."
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

$branchMap = [];
try {
    $rows = $pdo->query("SELECT [branch], [branch_code] FROM [IPROM].[dbo].[branches]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $key = strtoupper(trim($r['branch']));
        $branchMap[$key] = $r['branch_code'];
    }
} catch (PDOException $e) {
    die("Failed to load branch lookup: " . $e->getMessage());
}

// ── Read CSV ──────────────────────────────────────────────────────────────────

$handle = fopen($csvFile, 'r');
if (!$handle) die("Cannot open CSV.");

// Skip header row
fgetcsv($handle);

$errors        = [];
$count         = 0;
$skipped       = 0;
$branchMissing = [];

// Per-person ID maps (keyed by LASTNAME|FIRSTNAME|BIRTHDAY)
$employeeIdMap      = [];
$rovingGroupIdMap   = []; // for MULTI BRANCH + HYBRID
$multiBrandGroupIdMap = []; // for MULTI BRAND + HYBRID

$sql = "
    INSERT INTO [IPROM].[dbo].[employee_info] (
        [first_name],
        [last_name],
        [middle_name],
        [suffix],
        [gender],
        [birthday],
        [branch],
        [brand],
        [employment_status],
        [sub_status],
        [agency],
        [date_hired],
        [start_date],
        [end_date],
        [remarks],
        [employee_id],
        [roving_group_id],
        [multi_brand_group_id],
        [status],
        [hidden],
        [created_at],
        [updated_at],
        [assignment_date],
        [last_assigned_by],
        [last_updated_by]
    ) VALUES (
        :first_name,
        :last_name,
        :middle_name,
        :suffix,
        :gender,
        :birthday,
        :branch,
        :brand,
        :employment_status,
        :sub_status,
        :agency,
        :date_hired,
        :start_date,
        :end_date,
        :remarks,
        :employee_id,
        :roving_group_id,
        :multi_brand_group_id,
        'ACTIVE',
        0,
        GETDATE(),
        GETDATE(),
        GETDATE(),
        'SYSTEM',
        'SYSTEM'
    )
";

$stmt = $pdo->prepare($sql);

while (($row = fgetcsv($handle)) !== false) {

    // Convert from Windows-1252 (Excel CSV) → UTF-8 so Ñ/ñ and accents survive
    $row = toUtf8($row);

    // Skip fully empty rows
    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) {
        $skipped++;
        continue;
    }

    // Pad to 15 columns in case of short rows
    while (count($row) < 15) $row[] = '';

    [
        $branch,
        $lastName,
        $firstName,
        $mi,
        $suffix,
        $gender,
        $birthday,
        $dateHired,
        $branchDeployed,  // role/position title → stored in remarks
        $brand,
        $employmentStatus,
        $subStatus,
        $agency,
        $from,
        $to
    ] = $row;

    $subStatusNorm = strtoupper(clean($subStatus));

    // ── Resolve branch name → branch_code ────────────────────────────────────
    $branchKey  = strtoupper(clean($branch));
    $branchCode = $branchMap[$branchKey] ?? null;

    if ($branchCode === null && $branchKey !== '') {
        $branchMissing[$branchKey] = true;
    }

    // ── Resolve employee_id (shared across all rows for the same person) ──────
    $employeeId = resolveGroupId($lastName, $firstName, $birthday, 'EMP', $employeeIdMap);

    // ── Resolve roving_group_id (MULTI BRANCH or HYBRID only) ────────────────
    $rovingGroupId = null;
    if (in_array($subStatusNorm, ['MULTI BRANCH', 'HYBRID'])) {
        $rovingGroupId = resolveGroupId($lastName, $firstName, $birthday, 'ROV', $rovingGroupIdMap);
    }

    // ── Resolve multi_brand_group_id (MULTI BRAND or HYBRID only) ────────────
    $multiBrandGroupId = null;
    if (in_array($subStatusNorm, ['MULTI BRAND', 'HYBRID'])) {
        $multiBrandGroupId = resolveGroupId($lastName, $firstName, $birthday, 'MBR', $multiBrandGroupIdMap);
    }

    $params = [
        ':first_name'           => clean($firstName)          ?: null,
        ':last_name'            => clean($lastName)           ?: null,
        ':middle_name'          => clean($mi)                 ?: null,
        ':suffix'               => clean($suffix)             ?: null,
        ':gender'               => strtoupper(clean($gender)) ?: null,
        ':birthday'             => toSqlDate($birthday),
        ':branch'               => $branchCode,
        ':brand'                => strtoupper(clean($brand))            ?: null,
        ':employment_status'    => strtoupper(clean($employmentStatus)) ?: null,
        ':sub_status'           => $subStatusNorm                       ?: null,
        ':agency'               => clean($agency)             ?: null,
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

// ── Output ────────────────────────────────────────────────────────────────────
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
        <li>❌ <strong><?= count($errors) ?></strong> insert error(s).</li>
        <li>⚠️ <strong><?= count($branchMissing) ?></strong> unmatched branch name(s) — stored as <code>NULL</code>.</li>
        <li>🪪 <strong><?= count($employeeIdMap) ?></strong> unique employee ID(s) generated.</li>
        <li>🔀 <strong><?= count($rovingGroupIdMap) ?></strong> roving group ID(s) generated (MULTI BRANCH / HYBRID).</li>
        <li>🏷️ <strong><?= count($multiBrandGroupIdMap) ?></strong> multi-brand group ID(s) generated (MULTI BRAND / HYBRID).</li>
    </ul>

    <?php if ($branchMissing): ?>
    <div class="alert alert-warning">
        <strong>Branch names in CSV not found in <code>branches</code> table:</strong>
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
    <h5 class="text-danger">Insert Errors</h5>
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