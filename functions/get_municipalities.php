<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$provinceCode = $_GET['province_code'] ?? '';
if (!$provinceCode) {
    echo json_encode([]);
    exit;
}

$pdo = qa_db();

$stmt = $pdo->prepare("{CALL PH_municipalities (?)}");
$stmt->bindValue(1, $provinceCode, PDO::PARAM_STR);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = array_map(function ($r) {
    return [
        'code' => (string)$r['Code'],
        'name' => $r['Municipality'],
    ];
}, $rows);

echo json_encode($result);