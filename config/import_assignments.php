<?php
/**
 * import_assignments.php
 * One-time CSV import into IPROM [dbo].[assignment]
 * Place this file in your IPROM root (same level as db.php), then open in browser.
 * DELETE or MOVE this file after import is done.
 *
 * Expected CSV columns (row 1 = header, ignored):
 *   branch_name | brand_name | required_count
 *
 *   branch_name may be either the branch name OR branch code — both resolve to code.
 *
 * Hardcoded values:
 *   assigned_count → 0
 *   created_at     → GETDATE()
 *   updated_at     → GETDATE()
 *   timestamp      → GETDATE()
 *   updated_by     → 'SYSTEM'
 */

include_once 'db.php';

$csvFile = __DIR__ . '/needed_assignments_20260615.csv';
if (!file_exists($csvFile)) die("CSV file not found: $csvFile");

// ── Helpers ───────────────────────────────────────────────────────────────────

function clean(string $val): string {
    return trim(preg_replace('/\s+/', ' ', $val));
}

function toUtf8(array $row): array {
    return array_map(fn($v) => mb_convert_encoding($v, 'UTF-8', 'Windows-1252'), $row);
}

function upperClean(string $val): string {
    $val = clean($val);
    $replacements = [
        "\xC3\x91" => 'Ñ', "\xC3\xB1" => 'ñ', "\xD1" => 'Ñ', "\xF1" => 'ñ',
        "\xC3\x89" => 'É', "\xC3\xA9" => 'é', "\xC9" => 'É', "\xE9" => 'é',
        "\xC3\x81" => 'Á', "\xC3\xA1" => 'á', "\xC1" => 'Á', "\xE1" => 'á',
        "\xC3\x93" => 'Ó', "\xC3\xB3" => 'ó', "\xD3" => 'Ó', "\xF3" => 'ó',
        "\xC3\x8D" => 'Í', "\xC3\xAD" => 'í', "\xCD" => 'Í', "\xED" => 'í',
        "\xC3\x9A" => 'Ú', "\xC3\xBA" => 'ú', "\xDA" => 'Ú', "\xFA" => 'ú',
    ];
    $val = strtr($val, $replacements);
    return mb_strtoupper($val, 'UTF-8');
}

// ── Connect ───────────────────────────────────────────────────────────────────

$pdo = qa_db();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

// Branch lookup: UPPER(branch) → branch_code  AND  UPPER(branch_code) → branch_code
// Accepts either the human-readable branch name or the code from the CSV.
$branchMap = [];
try {
    $branchRows = $pdo->query("SELECT [branch], [branch_code] FROM [IPROM].[dbo].[branches]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($branchRows as $r) {
        $code = strtoupper(trim($r['branch_code']));
        $branchMap[strtoupper(trim($r['branch']))] = $code; // name  → code
        $branchMap[$code]                          = $code; // code  → code (self-resolve)
    }
} catch (PDOException $e) { die("Branch lookup failed: " . $e->getMessage()); }

// Existing assignments: UPPER(branch_code)|UPPER(brand_name) → true  (for duplicate detection)
$existingSet = [];
try {
    $rows = $pdo->query("SELECT [branch_name],[brand_name] FROM [IPROM].[dbo].[assignment]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $key = strtoupper(trim($r['branch_name'])) . '|' . mb_strtoupper(trim($r['brand_name']), 'UTF-8');
        $existingSet[$key] = true;
    }
} catch (PDOException $e) { die("Existing assignment lookup failed: " . $e->getMessage()); }

// ── PASS 1: Parse CSV ─────────────────────────────────────────────────────────

$handle = fopen($csvFile, 'r');
if (!$handle) die("Cannot open CSV.");
fgetcsv($handle); // skip header

$parsedRows    = [];
$csvKeys       = []; // "BRANCHCODE|BRAND" → count in CSV
$branchMissing = []; // raw branch values not resolvable in branchMap

while (($row = fgetcsv($handle)) !== false) {
    $row = toUtf8($row);
    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;
    while (count($row) < 3) $row[] = '';

    [$branchName, $brandName, $requiredCount] = $row;

    $branchRaw  = upperClean($branchName);
    $branchCode = $branchMap[$branchRaw] ?? null; // resolve name or code → code
    $brandNorm  = upperClean($brandName);
    $csvKey     = ($branchCode ?? $branchRaw) . '|' . $brandNorm;

    if ($branchRaw !== '') {
        $csvKeys[$csvKey] = ($csvKeys[$csvKey] ?? 0) + 1;

        if ($branchCode === null) {
            $branchMissing[$branchRaw] = true;
        }
    }

    $parsedRows[] = $row;
}
fclose($handle);

// ── Pre-flight analysis ───────────────────────────────────────────────────────

$alreadyExists = []; // combination already in DB
$newEntries    = []; // new, safe to insert
$withinCsvDups = []; // appears more than once in the CSV
$invalidBranch = []; // branch not resolvable

foreach ($csvKeys as $key => $count) {
    [$resolvedBranch] = explode('|', $key, 2);

    if (isset($branchMissing[$resolvedBranch])) {
        $invalidBranch[$key] = $count;
    } elseif (isset($existingSet[$key])) {
        $alreadyExists[$key] = $count;
    } else {
        $newEntries[$key] = $count;
        if ($count > 1) {
            $withinCsvDups[$key] = $count;
        }
    }
}

$preflightPassed = empty($alreadyExists) && empty($withinCsvDups) && empty($invalidBranch);

// ── PASS 2: Import ────────────────────────────────────────────────────────────

$inserted        = 0;
$skipped         = 0;
$duplicates      = [];
$rejections      = [];
$errors          = [];
$insertedThisRun = [];

$sql = "
    INSERT INTO [IPROM].[dbo].[assignment] (
        [branch_name], [brand_name], [required_count],
        [assigned_count], [updated_by],
        [created_at], [updated_at], [timestamp]
    ) VALUES (
        :branch_name, :brand_name, :required_count,
        0, 'SYSTEM',
        GETDATE(), GETDATE(), GETDATE()
    )
";
$stmt = $pdo->prepare($sql);

foreach ($parsedRows as $row) {

    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) {
        $skipped++;
        continue;
    }

    while (count($row) < 3) $row[] = '';
    [$branchName, $brandName, $requiredCount] = $row;

    $branchRaw    = upperClean($branchName);
    $branchCode   = $branchMap[$branchRaw] ?? null; // resolve name or code → code
    $brandNorm    = upperClean($brandName);
    $csvKey       = ($branchCode ?? $branchRaw) . '|' . $brandNorm;
    $requiredNorm = max(1, (int)clean($requiredCount)); // guard against 0 or blank

    // Skip blank branch
    if ($branchRaw === '') {
        $skipped++;
        continue;
    }

    // Reject: branch not resolvable
    if ($branchCode === null) {
        $rejections[] = ['row' => implode(', ', $row), 'reason' => "Branch '{$branchRaw}' not found in branches table."];
        continue;
    }

    // Duplicate: already in DB
    if (isset($existingSet[$csvKey])) {
        $duplicates[] = ['row' => implode(', ', $row), 'reason' => 'Combination already exists in database.'];
        continue;
    }

    // Duplicate: already inserted this run
    if (isset($insertedThisRun[$csvKey])) {
        $duplicates[] = ['row' => implode(', ', $row), 'reason' => 'Duplicate within CSV (first occurrence already imported).'];
        continue;
    }

    $params = [
        ':branch_name'    => $branchCode,   // always insert the resolved code
        ':brand_name'     => $brandNorm,
        ':required_count' => $requiredNorm,
    ];

    try {
        $stmt->execute($params);
        $insertedThisRun[$csvKey] = true;
        $inserted++;
    } catch (PDOException $e) {
        $errors[] = ['row' => implode(', ', $row), 'error' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assignment Import</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>
<body class="p-4">

<h4>Pre-flight Check</h4>

<?php if ($preflightPassed): ?>
    <div class="alert alert-success">✅ All checks passed — no conflicts detected.</div>
<?php else: ?>
    <div class="alert alert-warning">⚠️ Some issues found below — rows without conflicts were still imported.</div>
<?php endif; ?>

<?php /* ── Assignment status table ── */ ?>
<h5 class="mt-4">Assignments in CSV</h5>
<table class="table table-sm table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Branch Code</th>
            <th>Brand</th>
            <th class="text-center">Required</th>
            <th class="text-center">Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($newEntries as $key => $count):
            [$b, $br] = explode('|', $key, 2);
            $req = '—';
            foreach ($parsedRows as $r) {
                $resolvedCode = $branchMap[upperClean($r[0])] ?? null;
                if ($resolvedCode === $b && upperClean($r[1]) === $br) {
                    $req = max(1, (int)clean($r[2]));
                    break;
                }
            }
        ?>
        <tr class="<?= $count > 1 ? 'table-warning' : 'table-success' ?>">
            <td><?= htmlspecialchars($b)  ?></td>
            <td><?= htmlspecialchars($br) ?></td>
            <td class="text-center"><?= $req ?></td>
            <td class="text-center">
                <?php if ($count > 1): ?>
                    ⚠️ Duplicate within CSV — only first row imported
                <?php else: ?>
                    ✅ New — will be imported
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php foreach ($alreadyExists as $key => $count):
            [$b, $br] = explode('|', $key, 2); ?>
        <tr class="table-danger">
            <td><?= htmlspecialchars($b)  ?></td>
            <td><?= htmlspecialchars($br) ?></td>
            <td class="text-center">—</td>
            <td class="text-center">❌ Already in database — skipped</td>
        </tr>
        <?php endforeach; ?>

        <?php foreach ($invalidBranch as $key => $count):
            [$b, $br] = explode('|', $key, 2); ?>
        <tr class="table-danger">
            <td><?= htmlspecialchars($b)  ?></td>
            <td><?= htmlspecialchars($br) ?></td>
            <td class="text-center">—</td>
            <td class="text-center">❌ Branch not found in <code>branches</code> table</td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($csvKeys)): ?>
        <tr><td colspan="4" class="text-muted">No assignments found in CSV.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<hr>
<h4 class="d-flex align-items-center gap-3">
    Import Result
    <button class="btn btn-sm btn-success" onclick="exportResultsToExcel()">⬇️ Export to Excel</button>
</h4>
<ul>
    <li>✅ <strong><?= $inserted ?></strong> assignment(s) inserted successfully.</li>
    <li>⏭️ <strong><?= $skipped ?></strong> blank row(s) skipped.</li>
    <li>🔁 <strong><?= count($duplicates) ?></strong> row(s) skipped as duplicates.</li>
    <li>🚫 <strong><?= count($rejections) ?></strong> row(s) rejected (invalid branch).</li>
    <li>❌ <strong><?= count($errors) ?></strong> database error(s).</li>
</ul>

<?php if ($duplicates): ?>
<h5 class="text-secondary">🔁 Skipped — Duplicates</h5>
<table id="tbl-duplicates" class="table table-sm table-bordered table-secondary">
    <thead><tr><th>Row Data</th><th>Reason</th></tr></thead>
    <tbody>
        <?php foreach ($duplicates as $d): ?>
        <tr>
            <td><small><?= htmlspecialchars($d['row']) ?></small></td>
            <td><small><?= htmlspecialchars($d['reason']) ?></small></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($rejections): ?>
<h5 class="text-warning">🚫 Rejected During Import</h5>
<table id="tbl-rejections" class="table table-sm table-bordered table-warning">
    <thead><tr><th>Row Data</th><th>Reason</th></tr></thead>
    <tbody>
        <?php foreach ($rejections as $e): ?>
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

<div class="alert alert-danger mt-3">
    ⚠️ Delete or move <code>import_assignments.php</code> from your server now that the import is done.
</div>

<script>
function exportResultsToExcel() {
    const wb   = XLSX.utils.book_new();
    const date = new Date().toISOString().slice(0, 10);

    // Summary sheet
    const summaryData = [
        ['Metric', 'Count'],
        ['Inserted successfully',      <?= $inserted ?>],
        ['Blank rows skipped',         <?= $skipped ?>],
        ['Skipped — duplicates',       <?= count($duplicates) ?>],
        ['Rejected (invalid branch)',  <?= count($rejections) ?>],
        ['Database errors',            <?= count($errors) ?>],
    ];
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(summaryData), 'Summary');

    function sheetFromTable(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        return XLSX.utils.table_to_sheet(el, { raw: false });
    }

    const dupSheet = sheetFromTable('tbl-duplicates');
    const rejSheet = sheetFromTable('tbl-rejections');
    const errSheet = sheetFromTable('tbl-db-errors');

    if (dupSheet) XLSX.utils.book_append_sheet(wb, dupSheet, 'Duplicates');
    if (rejSheet) XLSX.utils.book_append_sheet(wb, rejSheet, 'Rejected');
    if (errSheet) XLSX.utils.book_append_sheet(wb, errSheet, 'DB Errors');

    XLSX.writeFile(wb, `import_assignments_result_${date}.xlsx`);
}
</script>

</body>
</html>