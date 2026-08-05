<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$role   = $_SESSION['role'] ?? '';
$branch = $_SESSION['branch'] ?? ''; // comma-delimited string

// Only branch managers get this bubble. Everyone else gets 0 — server-side
// gate, don't rely on the JS/UI check alone.
if (strtolower($role) !== 'branch_manager') {
    echo json_encode(['count' => 0]);
    exit;
}

$branchCodes = array_values(array_filter(array_map('trim', explode(',', $branch))));

if (empty($branchCodes)) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($branchCodes), '?'));

    // Adjust status value / column names below to match whatever
    // fetch_loa.php actually filters on for the "For Branch Verification" list.
    $sql = "SELECT COUNT(*) 
            FROM letters_of_advice
            WHERE branch_code IN ($placeholders)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($branchCodes);

    $count = (int)$stmt->fetchColumn();

    echo json_encode(['count' => $count]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['count' => 0, 'error' => 'Failed to fetch count']);
}