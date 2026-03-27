<?php
session_start();
include '../config/db.php';
$pdo = qa_db();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$branch = $data['branch'] ?? '';
$brand  = $data['brand'] ?? '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM assignment WHERE branch_name=? AND brand_name=?");
$stmt->execute([$branch, $brand]);
$exists = $stmt->fetchColumn() > 0;

echo json_encode(['exists' => $exists]);