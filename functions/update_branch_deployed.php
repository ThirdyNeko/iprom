<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../auth/require_login.php';

$pdo = qa_db();

$branch_code = $_POST['branch_code'] ?? null;
$deployed = isset($_POST['deployed']) ? (int)$_POST['deployed'] : null;

if (!$branch_code || $deployed === null || !in_array($deployed, [0, 1], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE branches SET deployed = ? WHERE branch_code = ?");
    $stmt->execute([$deployed, $branch_code]);

    echo json_encode(['success' => true, 'message' => 'Deployment status updated.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
}