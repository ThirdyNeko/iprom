<?php
require_once '../config/db.php';
$pdo = qa_db();

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode([]);
    exit;
}

// =========================
// GET MAIN EMPLOYEE
// =========================
$stmt = $pdo->prepare("
    SELECT *
    FROM employee_info
    WHERE id = :id
");
$stmt->execute([':id' => $id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    echo json_encode([]);
    exit;
}

// =========================
// GET MULTI BRANCH (ROVING)
// =========================
$rovingBranches = [];

if (!empty($employee['roving_group_id'])) {
    $stmt = $pdo->prepare("
        SELECT branch
        FROM employee_info
        WHERE roving_group_id = :gid
          AND id != :id
    ");
    $stmt->execute([
        ':gid' => $employee['roving_group_id'],
        ':id'  => $employee['id']
    ]);

    $rovingBranches = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// =========================
// GET MULTI BRAND
// =========================
$multiBrands = [];

if (!empty($employee['multi_brand_group_id'])) {
    $stmt = $pdo->prepare("
        SELECT brand
        FROM employee_info
        WHERE multi_brand_group_id = :gid
          AND id != :id
    ");
    $stmt->execute([
        ':gid' => $employee['multi_brand_group_id'],
        ':id'  => $employee['id']
    ]);

    $multiBrands = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// =========================
// ATTACH TO RESPONSE
// =========================
$employee['roving_branches'] = $rovingBranches;
$employee['multi_brands'] = $multiBrands;

// =========================
// RETURN JSON
// =========================
echo json_encode($employee);