<?php
session_start();
include '../config/db.php';
include '../auth/require_login.php';

header('Content-Type: application/json');

$pdo = qa_db();

$id    = trim($_POST['id'] ?? '');
$brand = strtoupper(trim($_POST['brand'] ?? ''));
$isEdit = $id !== '';

if ($brand === '') {
    echo json_encode(['success' => false, 'message' => 'Brand name is required.']);
    exit;
}

// DUPLICATE CHECK
$dupSql = "SELECT COUNT(*) FROM brands WHERE brand = :brand" . ($isEdit ? " AND id != :id" : "");
$dupStmt = $pdo->prepare($dupSql);
$dupParams = [':brand' => $brand];
if ($isEdit) $dupParams[':id'] = $id;
$dupStmt->execute($dupParams);

if ((int) $dupStmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => "\"$brand\" already exists."]);
    exit;
}

try {
    if ($isEdit) {
        $stmt = $pdo->prepare("UPDATE brands SET brand = :brand WHERE id = :id");
        $stmt->execute([':brand' => $brand, ':id' => $id]);
        echo json_encode(['success' => true, 'message' => "$brand has been updated."]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO brands (brand, status) VALUES (:brand, 1)");
        $stmt->execute([':brand' => $brand]);
        echo json_encode(['success' => true, 'message' => "$brand has been added."]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}