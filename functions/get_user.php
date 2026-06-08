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
        status,
        created_at,
        updated_at
    FROM users
    WHERE username = ?
");

$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// All branches (for the full checkbox list)
$allBranchesStmt = $pdo->query("SELECT branch_code, branch FROM branches ORDER BY branch");
$user['branch_names'] = $allBranchesStmt->fetchAll(PDO::FETCH_KEY_PAIR); // [code => name]

echo json_encode($user);