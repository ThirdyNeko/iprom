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

echo json_encode($user);