<?php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
include '../auth/require_login.php';

$pdo = qa_db();

$role = strtolower($_SESSION['role'] ?? '');

// Only branch managers and regional managers get this bubble. Everyone
// else gets 0 — server-side gate, don't rely on the JS/UI check alone.
if (!in_array($role, ['branch_manager', 'regional_manager'], true)) {
    echo json_encode(['count' => 0]);
    exit;
}

if ($role === 'regional_manager') {
    // Regional managers cover every branch in their region. login.php
    // resolves the region into branch codes and stores them as an array in
    // user_branches; $_SESSION['branch'] is null for them.
    $branchCodes = array_values(array_filter(
        array_map('trim', $_SESSION['user_branches'] ?? [])
    ));
} else {
    $branch      = $_SESSION['branch'] ?? ''; // comma-delimited string
    $branchCodes = array_values(array_filter(array_map('trim', explode(',', $branch))));
}

// No branches = nothing to count, never "everything"
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