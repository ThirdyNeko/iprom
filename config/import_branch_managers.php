<?php
/**
 * import_branch_managers.php
 * One-time CSV import into IPROM_2 [dbo].[users]
 * Place this file in your IPROM root (same level as db.php), then open in browser.
 * DELETE or MOVE this file after import is done.
 *
 * Expected CSV columns (row 1 = header, ignored):
 *   Branch | First Name | Middle Name | Last Name | Suffix | Position | Username
 */

include_once 'db.php';

$csvFile = __DIR__ . '/Branch_Managers_Sheet1_.csv';
if (!file_exists($csvFile)) die("CSV file not found: $csvFile");

// Default password given to every imported manager.
// first_login = 1 forces them to change it on first login.
const DEFAULT_PASSWORD = 'Password123';

// Role assigned to every row in this sheet.
const DEFAULT_ROLE = 'branch_manager';

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

// Existing usernames → true (for duplicate detection)
$existingUsernames = [];
try {
    $rows = $pdo->query("SELECT [username] FROM [IPROM_2].[dbo].[users]")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $existingUsernames[mb_strtoupper(trim($r['username']), 'UTF-8')] = true;
    }
} catch (PDOException $e) { die("Existing user lookup failed: " . $e->getMessage()); }

// ── PASS 1: Parse CSV ─────────────────────────────────────────────────────────

$handle = fopen($csvFile, 'r');
if (!$handle) die("Cannot open CSV.");
fgetcsv($handle); // skip header row

$parsedRows = [];
// username → ['username' => ..., 'branch' => ..., 'count' => N]
$csvEntries = [];

while (($row = fgetcsv($handle)) !== false) {
    $row = toUtf8($row);
    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;
    while (count($row) < 7) $row[] = '';

    [
        $branch, $firstName, $middleName, $lastName,
        $suffix, $position, $username
    ] = $row;

    $usernameNorm = upperClean($username);
    $branchNorm   = upperClean($branch);

    if ($usernameNorm !== '') {
        if (!isset($csvEntries[$usernameNorm])) {
            $csvEntries[$usernameNorm] = [
                'username' => $usernameNorm,
                'branch'   => $branchNorm,
                'count'    => 0,
            ];
        }
        $csvEntries[$usernameNorm]['count']++;
    }

    $parsedRows[] = $row;
}
fclose($handle);

// ── Pre-flight analysis ───────────────────────────────────────────────────────

$alreadyExists = []; // username already in DB
$newEntries    = []; // not in DB → will be inserted
$withinCsvDups = []; // appears more than once inside the CSV itself

foreach ($csvEntries as $key => $meta) {
    if (isset($existingUsernames[$key])) {
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
$insertedThisRun = []; // username → true

$hashedPassword = password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT);

$sql = "
    INSERT INTO [IPROM_2].[dbo].[users]
        ([username], [password], [role], [branch], [brand], [position],
         [first_name], [last_name], [department], [first_login], [status],
         [middle_name], [suffix], [created_at], [updated_at])
    VALUES
        (:username, :password, :role, :branch, :brand, :position,
         :first_name, :last_name, :department, :first_login, :status,
         :middle_name, :suffix, GETDATE(), GETDATE())
";
$stmt = $pdo->prepare($sql);

foreach ($parsedRows as $row) {

    if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) {
        $skipped++;
        continue;
    }

    while (count($row) < 7) $row[] = '';

    [
        $branch, $firstName, $middleName, $lastName,
        $suffix, $position, $username
    ] = $row;

    $branchNorm    = upperClean($branch);
    $firstNameNorm = upperClean($firstName);
    $middleNorm    = upperClean($middleName);
    $lastNameNorm  = upperClean($lastName);
    $suffixNorm    = upperClean($suffix);
    $positionNorm  = upperClean($position);
    $usernameNorm  = upperClean($username);

    // Skip rows missing required fields
    if ($branchNorm === '' || $firstNameNorm === '' || $lastNameNorm === '' || $usernameNorm === '') {
        $skipped++;
        continue;
    }

    // Duplicate: already in DB
    if (isset($existingUsernames[$usernameNorm])) {
        $duplicates[] = [
            'row'    => implode(', ', $row),
            'reason' => 'Username already exists in database.',
        ];
        continue;
    }

    // Duplicate: inserted earlier in THIS import run
    if (isset($insertedThisRun[$usernameNorm])) {
        $duplicates[] = [
            'row'    => implode(', ', $row),
            'reason' => 'Duplicate within CSV (first occurrence already imported).',
        ];
        continue;
    }

    $params = [
        ':username'     => $usernameNorm,
        ':password'     => $hashedPassword,
        ':role'         => DEFAULT_ROLE,
        ':branch'       => $branchNorm,
        ':brand'        => null,
        ':position'     => $positionNorm,
        ':first_name'   => $firstNameNorm,
        ':last_name'    => $lastNameNorm,
        ':department'   => null,
        ':first_login'  => 1,
        ':status'       => 1,
        ':middle_name'  => $middleNorm ?: null,
        ':suffix'       => $suffixNorm ?: null,
    ];

    try {
        $stmt->execute($params);
        $insertedThisRun[$usernameNorm] = true;
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
    <title>Branch Managers Import</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<h4>Pre-flight Check</h4>

<?php if ($preflightPassed): ?>
    <div class="alert alert-success">✅ All checks passed — no conflicts detected.</div>
<?php else: ?>
    <div class="alert alert-warning">⚠️ Some issues found below — rows without conflicts were still imported.</div>
<?php endif; ?>

<div class="alert alert-info">
    ℹ️ All imported accounts get the default password <code><?= htmlspecialchars(DEFAULT_PASSWORD) ?></code>
    and role <code><?= htmlspecialchars(DEFAULT_ROLE) ?></code>, with <code>first_login = 1</code> so they must change
    their password on first login.
</div>

<h5 class="mt-4">Branch Managers in CSV</h5>
<table class="table table-sm table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Username</th>
            <th>Branch</th>
            <th class="text-center">CSV Rows</th>
            <th class="text-center">Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($newEntries as $meta): ?>
        <tr class="<?= $meta['count'] > 1 ? 'table-warning' : 'table-success' ?>">
            <td><?= htmlspecialchars($meta['username']) ?></td>
            <td><?= htmlspecialchars($meta['branch']) ?></td>
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
            <td><?= htmlspecialchars($meta['username']) ?></td>
            <td><?= htmlspecialchars($meta['branch']) ?></td>
            <td class="text-center"><?= $meta['count'] ?></td>
            <td class="text-center">❌ Already in database — skipped</td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($csvEntries)): ?>
        <tr><td colspan="4" class="text-muted">No branch managers found in CSV.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<hr>
<h4>Import Result</h4>
<ul>
    <li>✅ <strong><?= $inserted ?></strong> user(s) inserted successfully.</li>
    <li>⏭️ <strong><?= $skipped ?></strong> blank/incomplete row(s) skipped.</li>
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
    ⚠️ Delete or move <code>import_branch_managers.php</code> from your server now that the import is done.
</div>

</body>
</html>