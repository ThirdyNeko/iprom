<?php
require_once '../config/db.php';
header('Content-Type: application/json');

$pdo = qa_db();
$input = json_decode(file_get_contents('php://input'), true);

$branch = $input['branch'] ?? '';
$brand  = $input['brand'] ?? '';

if (!$branch || !$brand) {
    echo json_encode(['exists' => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt
    FROM assignment
    WHERE branch_name = :branch
      AND brand_name  = :brand
");
$stmt->execute([
    ':branch' => $branch,
    ':brand'  => $brand
]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['exists' => ($result['cnt'] > 0)]);