<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$municipalityCode = $_GET['municipality_code'] ?? '';
if (!$municipalityCode) {
    echo json_encode([]);
    exit;
}

$pdo = qa_db();

$stmt = $pdo->prepare("{CALL PH_barangay (?)}");
$stmt->bindValue(1, $municipalityCode, PDO::PARAM_STR);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = array_map(function ($r) {
    return [
        'code' => (string)$r['Code'],
        'name' => $r['Barangay'],
    ];
}, $rows);

echo json_encode($result);