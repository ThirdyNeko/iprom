<?php
include '../config/db.php';
$pdo = qa_db();

$username = $_POST['username'] ?? null;

if (!$username) {
    echo json_encode(['error' => 'Missing username']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        first_name,
        last_name,
        position,
        branch,
        role,
        created_at,
        updated_at
    FROM users
    WHERE username = ?
");

$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch branch names using the stored codes
$branchCodes = !empty($user['branch']) ? explode(',', $user['branch']) : [];

$branchNames = [];
if (!empty($branchCodes)) {
    $placeholders = implode(',', array_fill(0, count($branchCodes), '?'));
    $branchStmt = $pdo->prepare("
        SELECT branch_code, branch 
        FROM branches 
        WHERE branch_code IN ($placeholders)
    ");
    $branchStmt->execute($branchCodes);
    $branchNames = $branchStmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

$user['branch_names'] = $branchNames;

echo json_encode($user);