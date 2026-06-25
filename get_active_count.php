<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    exit;
}

include_once __DIR__ . '/config/db.php';
$pdo = qa_db();

$excludedUsernames = ['QA_HR_ADMIN', 'QA_HR_SUPERVISOR', 'QA_HR_STAFF'];
$placeholders = implode(',', array_fill(0, count($excludedUsernames), '?'));

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM active_sessions
    WHERE role != 'super_admin'
      AND username NOT IN ($placeholders)
      AND last_seen >= DATEADD(MINUTE, -2, GETDATE())
");
$stmt->execute($excludedUsernames);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['count' => (int)($row['total'] ?? 0)]);