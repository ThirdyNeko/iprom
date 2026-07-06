<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();

$stmt = $pdo->prepare("EXEC PH_Province");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = array_map(function ($r) {
    return [
        'code' => (string)$r['Code'],
        'name' => $r['Province'],
    ];
}, $rows);

echo json_encode($result);