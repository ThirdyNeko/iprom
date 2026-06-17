<?php
/**
 * import_agencies.php
 * One-time CSV import into IPROM [dbo].[agencies]
 * Place this file in your IPROM root (same level as db.php), then open in browser.
 * DELETE or MOVE this file after import is done.
 *
 * Expected CSV columns (row 1 = header, ignored):
 *   agencies | contact_person | contact_number | email | tel_number | status
 */

include_once 'db.php';

$csvFile = __DIR__ . '/Sample_Agency_data_Sheet1_.csv';
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

// Composite key for duplicate detection: "AGENCY NAME||CONTACT PERSON"
function makeKey(string $agency, string $contactPerson): string {
    return $agency . '||' . $contactPerson;
}

// ── Connect ───────────────────────────────────────────────────────────────────

$pdo = qa_db();

// Existing records: combined key → true  (for duplicate detection)
$existingSet = [];
try {
    $rows = $pdo->query("SELECT [agencies], [contact_person] FROM [IPROM].[dbo].[agencies]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $key = makeKey(
            mb_strtoupper(trim($r['agencies']),        'UTF-8'),
            mb_strtoupper(trim($r['contact_person'] ?? ''), 'UTF-8')
        );
        $existingSet[$key] = true;
    }
} catch (PDOException $e) { die("Existing agency lookup failed: " . $e->getMessage()); }

// ── PASS 1: Parse CSV ─────────────────────────────────────────────────────────

$handle = fopen($csvFile, 'r');
if (!$handle) die("Cannot open CSV.");
fgetcsv($handle); // skip header row

$parsedRows = [];
// combined key → ['agency' => ..., 'contact_person' => ..., 'count' => N]
$csvEntries = [];

while (($row = fgetcsv($handle)) !== false) {
    $row = toUtf8($row);
    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;
    while (count($row) < 6) $row[] = '';

    [
        $agencyRaw, $contactPerson, $contactNumber,
        $email, $telNumber, $status
    ] = $row;

    $agencyNorm        = upperClean($agencyRaw);
    $contactPersonNorm = upperClean($contactPerson);

    if ($agencyNorm !== '') {
        $key = makeKey($agencyNorm, $contactPersonNorm);
        if (!isset($csvEntries[$key])) {
            $csvEntries[$key] = [
                'agency'         => $agencyNorm,
                'contact_person' => $contactPersonNorm,
                'count'          => 0,
            ];
        }
        $csvEntries[$key]['count']++;
    }

    $parsedRows[] = $row;
}
fclose($handle);

// ── Pre-flight analysis ───────────────────────────────────────────────────────

$alreadyExists = []; // in DB  → would be duplicate
$newEntries    = []; // not in DB → will be inserted
$withinCsvDups = []; // appears more than once inside the CSV itself

foreach ($csvEntries as $key => $meta) {
    if (isset($existingSet[$key])) {
        $alreadyExists[$key] = $meta;
    } else {
        $newEntries[$key] = $meta;
        if ($meta['count'] > 1) {
            $withinCsvDups[$key] = $meta;
        }
    }
}

$preflightPassed = empty($alreadyExists) && empty($withinCsvDups);

// ── PASS 2: Import ────────────────────────────────────────────────────────────

$inserted        = 0;
$skipped         = 0;
$duplicates      = [];
$errors          = [];
$insertedThisRun = []; // combined key → true

$sql = "
    INSERT INTO [IPROM].[dbo].[agencies]
        ([agencies], [contact_person], [contact_number], [email], [tel_number], [status])
    VALUES
        (:agencies, :contact_person, :contact_number, :email, :tel_number, :status)
";
$stmt = $pdo->prepare($sql);

foreach ($parsedRows as $row) {

    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) {
        $skipped++;
        continue;
    }

    while (count($row) < 6) $row[] = '';

    [
        $agencyRaw, $contactPerson, $contactNumber,
        $telNumber, $email, $status
    ] = $row;

    $agencyNorm        = upperClean($agencyRaw);
    $contactPersonNorm = upperClean($contactPerson);

    // Skip blank agency name
    if ($agencyNorm === '') {
        $skipped++;
        continue;
    }

    $key = makeKey($agencyNorm, $contactPersonNorm);

    // Duplicate: already in DB (same agency name + contact person)
    if (isset($existingSet[$key])) {
        $duplicates[] = [
            'row'    => implode(', ', $row),
            'reason' => 'Agency with the same name and contact person already exists in database.',
        ];
        continue;
    }

    // Duplicate: inserted earlier in THIS import run
    if (isset($insertedThisRun[$key])) {
        $duplicates[] = [
            'row'    => implode(', ', $row),
            'reason' => 'Duplicate within CSV (first occurrence already imported).',
        ];
        continue;
    }

    // status is a bit column: 1 = active, 0 = inactive
    $statusRaw  = mb_strtoupper(clean($status), 'UTF-8');
    $statusNorm = match(true) {
        in_array($statusRaw, ['0', 'INACTIVE', 'DISABLED', 'NO']) => 0,
        default                                                     => 1,
    };

    $params = [
        ':agencies'       => $agencyNorm,
        ':contact_person' => $contactPersonNorm ?: null,
        ':contact_number' => clean($contactNumber) ?: null,
        ':email'          => clean($email)          ?: null,
        ':tel_number'     => clean($telNumber)      ?: null,
        ':status'         => $statusNorm,
    ];

    try {
        $stmt->execute($params);
        $insertedThisRun[$key] = true;
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
    <title>Agency Import</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<h4>Pre-flight Check</h4>

<?php if ($preflightPassed): ?>
    <div class="alert alert-success">✅ All checks passed — no conflicts detected.</div>
<?php else: ?>
    <div class="alert alert-warning">⚠️ Some issues found below — rows without conflicts were still imported.</div>
<?php endif; ?>

<h5 class="mt-4">Agencies in CSV</h5>
<table class="table table-sm table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Agency Name</th>
            <th>Contact Person</th>
            <th class="text-center">CSV Rows</th>
            <th class="text-center">Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($newEntries as $meta): ?>
        <tr class="<?= $meta['count'] > 1 ? 'table-warning' : 'table-success' ?>">
            <td><?= htmlspecialchars($meta['agency']) ?></td>
            <td><?= htmlspecialchars($meta['contact_person'] ?: '—') ?></td>
            <td class="text-center"><?= $meta['count'] ?></td>
            <td class="text-center">
                <?php if ($meta['count'] > 1): ?>
                    ⚠️ Duplicate within CSV — only first row imported
                <?php else: ?>
                    ✅ New — will be imported
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php foreach ($alreadyExists as $meta): ?>
        <tr class="table-danger">
            <td><?= htmlspecialchars($meta['agency']) ?></td>
            <td><?= htmlspecialchars($meta['contact_person'] ?: '—') ?></td>
            <td class="text-center"><?= $meta['count'] ?></td>
            <td class="text-center">❌ Already in database — skipped</td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($csvEntries)): ?>
        <tr><td colspan="4" class="text-muted">No agencies found in CSV.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<hr>
<h4>Import Result</h4>
<ul>
    <li>✅ <strong><?= $inserted ?></strong> agency/ies inserted successfully.</li>
    <li>⏭️ <strong><?= $skipped ?></strong> blank row(s) skipped.</li>
    <li>🔁 <strong><?= count($duplicates) ?></strong> row(s) skipped as duplicates.</li>
    <li>❌ <strong><?= count($errors) ?></strong> database error(s).</li>
</ul>

<?php if ($duplicates): ?>
<h5 class="text-secondary">🔁 Skipped — Duplicates</h5>
<table class="table table-sm table-bordered table-secondary">
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
    ⚠️ Delete or move <code>import_agencies.php</code> from your server now that the import is done.
</div>

</body>
</html>