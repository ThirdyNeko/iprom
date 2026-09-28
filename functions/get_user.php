<?php
include '../config/db.php';
$pdo = qa_db();

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        id,
        username,
        first_name,
        middle_name,
        last_name,
        suffix,
        position,
        branch,
        region,
        role,
        status,
        created_at,
        updated_at
    FROM users
    WHERE id = ?
");

$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

// All branches (for the full checkbox list)
$allBranchesStmt = $pdo->query("SELECT branch_code, branch FROM branches ORDER BY branch");
$user['branch_names'] = $allBranchesStmt->fetchAll(PDO::FETCH_KEY_PAIR); // [code => name]

// All regions (for the full checkbox list). Regions have no separate code
// column like branches do — the value stored in users.region IS the display
// text — so this is a {region: region} map rather than a {code: name} one.
$allRegionsStmt = $pdo->query("
    SELECT DISTINCT region
    FROM branches
    WHERE region IS NOT NULL
      AND region <> ''
    ORDER BY region
");
$regionList = $allRegionsStmt->fetchAll(PDO::FETCH_COLUMN);
$user['region_names'] = array_combine($regionList, $regionList);

echo json_encode($user);