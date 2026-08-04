<?php
session_start();
require_once '../config/db.php';
require_once '../auth/require_login.php';

$pdo = qa_db();

$branch = $_GET['branch'] ?? 'ALL';
$role = $_SESSION['role'] ?? '';
$sessionBranches = !empty($_SESSION['branch']) ? explode(',', $_SESSION['branch']) : [];

$sql = "SELECT employee_id AS id, first_name, last_name, biometric_number
        FROM employee_info
        WHERE (biometric_number IS NULL OR biometric_number = '') AND status = 'ACTIVE'";
        

$params = [];

// Branch managers / non-admin roles: force-scope to their session branches,
// regardless of what was selected client-side.
if ($role !== 'super_admin' && $role !== 'admin') {
    if (empty($sessionBranches)) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($sessionBranches), '?'));
    $sql .= " AND branch IN ($placeholders)";
    $params = $sessionBranches;
} elseif ($branch !== 'ALL' && $branch !== '') {
    $sql .= " AND branch = ?";
    $params[] = $branch;
}

$sql .= " ORDER BY last_name, first_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($rows);