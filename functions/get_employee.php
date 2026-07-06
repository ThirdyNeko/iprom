<?php
session_start();
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

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

$canPrintLOA = $isAdmin && !empty($employee['print_loa']) && $employee['print_loa'] == 1;

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
$employee['employee_id'] = $employee['employee_id'] ?? null; // ✅ ADD THIS

// ✅ NEW: personal / address fields (explicit fallback in case columns
// were just added and older rows return them as missing keys instead of NULL)
$employee['marital_status']      = $employee['marital_status'] ?? null;
$employee['contact_number']      = $employee['contact_number'] ?? null;
$employee['province']            = $employee['province'] ?? null;
$employee['province_name']       = $employee['province_name'] ?? null;
$employee['municipality']        = $employee['municipality'] ?? null;
$employee['municipality_name']   = $employee['municipality_name'] ?? null;
$employee['barangay']            = $employee['barangay'] ?? null;
$employee['barangay_name']       = $employee['barangay_name'] ?? null;
$employee['street']              = $employee['street'] ?? null;

// =========================
// RETURN JSON
// =========================
$employee['can_print_loa'] = $canPrintLOA;
echo json_encode($employee);