<?php
/**
 * update_biometric_numbers.php
 * One-time CSV update into IPROM_2 [dbo].[employee_info]
 * Place this file in your IPROM root (same level as db.php), then open in browser.
 * DELETE or MOVE this file after the update is done.
 *
 * Expected CSV columns (row 1 = header, ignored):
 *   ID | Biometric Number | Remarks
 *
 * Behavior:
 *   - [ID] is matched against [employee_id] in employee_info.
 *   - Only rows with Remarks = "OK" (case-insensitive) are applied.
 *   - Rows with blank Remarks or Remarks = "INVALID" (or anything other than OK) are skipped.
 *   - Only [biometric_number] is updated; everything else in the row is left alone.
 */

include_once 'db.php';

$csvFile = __DIR__ . '/Biometric_Numbers.csv';
if (!file_exists($csvFile)) die("CSV file not found: $csvFile");

// ── Helpers ───────────────────────────────────────────────────────────────────

function clean(string $val): string {
    return trim(preg_replace('/\s+/', ' ', $val));
}

function toUtf8(array $row): array {
    return array_map(function($v) {
        if (mb_check_encoding($v, 'UTF-8')) return $v;
        return mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
    }, $row);
}

function upperClean(string $val): string {
    return mb_strtoupper(clean($val), 'UTF-8');
}

// ── Connect ───────────────────────────────────────────────────────────────────

$pdo = qa_db();

// Existing employee_id → true (for pre-flight "not found" detection)
$existingEmployeeIds = [];
try {
    $rows = $pdo->query("SELECT [employee_id] FROM [IPROM_2].[dbo].[employee_info]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if ($r['employee_id'] === null) continue;
        $existingEmployeeIds[mb_strtoupper(trim($r['employee_id']), 'UTF-8')] = true;
    }
} catch (PDOException $e) { die("Existing employee lookup failed: " . $e->getMessage()); }

// ── PASS 1: Parse CSV ─────────────────────────────────────────────────────────

$handle = fopen($csvFile, 'r');
if (!$handle) die("Cannot open CSV.");

// Strip UTF-8 BOM if present on the header line
$firstLine = fgets($handle);
$firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
rewind($handle);
fgetcsv($handle); // skip header row (BOM only affects first cell of header, which we discard anyway)

$parsedRows = [];
// employeeId → ['id' => ..., 'biometric' => ..., 'remarks' => ..., 'count' => N]
$csvEntries = [];

while (($row = fgetcsv($handle)) !== false) {
    $row = toUtf8($row);
    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;
    while (count($row) < 3) $row[] = '';

    [$id, $biometric, $remarks] = $row;

    $idNorm      = upperClean($id);
    $biometricNorm = clean($biometric);
    $remarksNorm = upperClean($remarks);

    if ($idNorm !== '') {
        if (!isset($csvEntries[$idNorm])) {
            $csvEntries[$idNorm] = [
                'id'         => $idNorm,
                'biometric'  => $biometricNorm,
                'remarks'    => $remarksNorm,
                'count'      => 0,
            ];
        }
        $csvEntries[$idNorm]['count']++;
    }

    $parsedRows[] = $row;
}
fclose($handle);

// ── Pre-flight analysis ───────────────────────────────────────────────────────

$notFound      = []; // employee_id not in DB
$toUpdate      = []; // OK, found in DB → will be updated
$skippedRemark = []; // blank or INVALID remarks
$withinCsvDups = []; // appears more than once inside the CSV itself

foreach ($csvEntries as $key => $meta) {
    if ($meta['count'] > 1) {
        $withinCsvDups[$key] = $meta;
    }

    if ($meta['remarks'] !== 'OK') {
        $skippedRemark[$key] = $meta;
        continue;
    }

    if (!isset($existingEmployeeIds[$key])) {
        $notFound[$key] = $meta;
        continue;
    }

    $toUpdate[$key] = $meta;
}

$preflightPassed = empty($notFound) && empty($withinCsvDups);

// ── PASS 2: Update ────────────────────────────────────────────────────────────

$updated         = 0;
$skipped         = 0;
$notOk           = [];
$missing         = [];
$errors          = [];
$updatedThisRun  = []; // employee_id → true

$sql = "
    UPDATE [IPROM_2].[dbo].[employee_info]
    SET [biometric_number] = :biometric_number,
        [updated_at] = GETDATE()
    WHERE [employee_id] = :employee_id
";
$stmt = $pdo->prepare($sql);

foreach ($parsedRows as $row) {

    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) {
        $skipped++;
        continue;
    }

    while (count($row) < 3) $row[] = '';

    [$id, $biometric, $remarks] = $row;

    $idNorm        = upperClean($id);
    $biometricNorm = clean($biometric);
    $remarksNorm   = upperClean($remarks);

    // Missing required ID
    if ($idNorm === '') {
        $skipped++;
        continue;
    }

    // Skip blank or INVALID remarks
    if ($remarksNorm !== 'OK') {
        $notOk[] = [
            'row'    => implode(', ', $row),
            'reason' => $remarksNorm === '' ? 'Blank remarks — skipped.' : "Remarks = \"$remarksNorm\" — skipped.",
        ];
        continue;
    }

    // Not found in DB
    if (!isset($existingEmployeeIds[$idNorm])) {
        $missing[] = [
            'row'    => implode(', ', $row),
            'reason' => 'employee_id not found in database.',
        ];
        continue;
    }

    // Avoid redundant repeat updates if the same ID appears twice with OK in the CSV
    if (isset($updatedThisRun[$idNorm])) {
        $notOk[] = [
            'row'    => implode(', ', $row),
            'reason' => 'Duplicate within CSV (first OK occurrence already applied).',
        ];
        continue;
    }

    $params = [
        ':biometric_number' => $biometricNorm !== '' ? $biometricNorm : null,
        ':employee_id'      => $idNorm,
    ];

    try {
        $stmt->execute($params);
        $updatedThisRun[$idNorm] = true;
        $updated++;
    } catch (PDOException $e) {
        $errors[] = ['row' => implode(', ', $row), 'error' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Biometric Number Update</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<h4>Pre-flight Check</h4>

<?php if ($preflightPassed): ?>
    <div class="alert alert-success">✅ All checks passed — no conflicts detected.</div>
<?php else: ?>
    <div class="alert alert-warning">⚠️ Some issues found below — rows without conflicts were still updated.</div>
<?php endif; ?>

<div class="alert alert-info">
    ℹ️ Only rows with <code>Remarks = OK</code> are applied. Blank and <code>INVALID</code> rows are skipped entirely
    (no changes made to those employees). Only the <code>biometric_number</code> column is touched.
</div>

<h5 class="mt-4">Summary from CSV</h5>
<table class="table table-sm table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Category</th>
            <th class="text-center">Count</th>
        </tr>
    </thead>
    <tbody>
        <tr class="table-success"><td>OK &amp; found in DB (will update)</td><td class="text-center"><?= count($toUpdate) ?></td></tr>
        <tr class="table-danger"><td>OK but employee_id not found in DB</td><td class="text-center"><?= count($notFound) ?></td></tr>
        <tr class="table-secondary"><td>Blank / INVALID remarks (skipped)</td><td class="text-center"><?= count($skippedRemark) ?></td></tr>
        <tr class="<?= $withinCsvDups ? 'table-warning' : '' ?>"><td>Duplicate IDs within CSV</td><td class="text-center"><?= count($withinCsvDups) ?></td></tr>
    </tbody>
</table>

<?php if ($notFound): ?>
<h5 class="text-danger mt-4">❌ Not Found in Database</h5>
<table class="table table-sm table-bordered">
    <thead><tr><th>Employee ID</th><th>Biometric Number</th></tr></thead>
    <tbody>
        <?php foreach ($notFound as $meta): ?>
        <tr>
            <td><?= htmlspecialchars($meta['id']) ?></td>
            <td><?= htmlspecialchars($meta['biometric']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<hr>
<h4>Update Result</h4>
<ul>
    <li>✅ <strong><?= $updated ?></strong> employee record(s) updated successfully.</li>
    <li>⏭️ <strong><?= $skipped ?></strong> blank/incomplete row(s) skipped.</li>
    <li>🚫 <strong><?= count($notOk) ?></strong> row(s) skipped (blank/INVALID remarks or in-CSV duplicate).</li>
    <li>❓ <strong><?= count($missing) ?></strong> row(s) skipped (employee_id not found).</li>
    <li>❌ <strong><?= count($errors) ?></strong> database error(s).</li>
</ul>

<?php if ($notOk): ?>
<h5 class="text-secondary">🚫 Skipped — Not OK / Duplicate</h5>
<table class="table table-sm table-bordered table-secondary">
    <thead><tr><th>Row Data</th><th>Reason</th></tr></thead>
    <tbody>
        <?php foreach ($notOk as $d): ?>
        <tr>
            <td><small><?= htmlspecialchars($d['row']) ?></small></td>
            <td><small><?= htmlspecialchars($d['reason']) ?></small></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($missing): ?>
<h5 class="text-danger">❓ Skipped — employee_id Not Found</h5>
<table class="table table-sm table-bordered table-striped">
    <thead><tr><th>Row Data</th><th>Reason</th></tr></thead>
    <tbody>
        <?php foreach ($missing as $m): ?>
        <tr>
            <td><small><?= htmlspecialchars($m['row']) ?></small></td>
            <td><small><?= htmlspecialchars($m['reason']) ?></small></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($errors): ?>
<h5 class="text-danger">❌ Database Errors</h5>
<table class="table table-sm table-bordered table-striped">
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
    ⚠️ Delete or move <code>update_biometric_numbers.php</code> from your server now that the update is done.
</div>

</body>
</html>